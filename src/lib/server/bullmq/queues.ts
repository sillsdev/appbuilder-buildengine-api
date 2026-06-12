import { Queue } from 'bullmq';
import { BullMQOtel } from 'bullmq-otel';
import { Redis } from 'ioredis';
import type { BuildJob, PollJob, PublishJob, RecurringJob, S3Job, StartupJob } from './types';
import { QueueName } from './types';
import { env } from '$env/dynamic/private';
import OTEL from '$lib/otel';

class Connection {
  private conn: Redis;
  private connected: boolean;
  private heartbeatInterval: NodeJS.Timeout | null = null;
  constructor(isQueueConnection = false, keyPrefix?: string) {
    this.conn = new Redis({
      host: process.env.NODE_ENV === 'development' ? 'localhost' : process.env.VALKEY_HOST,
      maxRetriesPerRequest: isQueueConnection ? undefined : null,
      keyPrefix
    });
    this.connected = false;
    this.conn.on('close', () => {
      OTEL.instance.logger.info('Valkey connection closed', {
        isQueueConnection
      });
      this.connected = false;
    });
    this.conn.on('connect', () => {
      OTEL.instance.logger.info('Valkey connection established', {
        isQueueConnection
      });
      this.connected = true;
    });
    this.conn.on('error', (err) => {
      OTEL.instance.logger.error('Valkey connection error', {
        error: err.message,
        isQueueConnection
      });
      this.connected = false;
      if (err.message.includes('ENOTFOUND')) {
        console.error('Fatal Valkey connection', err);
        process.exit(1);
      } else if (!err.message.includes('ECONNREFUSED')) {
        console.error('Valkey connection error', err);
      }
    });
    this.heartbeatInterval = setInterval(() => {
      if (this.connected) {
        this.conn
          .ping()
          .then(() => {
            this.connected = true;
          })
          .catch((err) => {
            if (this.connected) {
              console.error(err);
              console.log('Valkey disconnected');
              this.connected = false;
              OTEL.instance.logger.error('Valkey disconnected', {
                error: err.message,
                isQueueConnection
              });
            }
          });
      }
    }, 10000);
    // Ensure the interval doesn't prevent Node from exiting
    this.heartbeatInterval.unref();
  }
  public IsConnected() {
    return this.connected;
  }

  public connection() {
    return this.conn;
  }

  public async close() {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
    }
    this.connected = false;
    await this.conn.quit();
  }
}

let _workerConnection: Connection | undefined = undefined;
let _queueConnection: Connection | undefined = undefined;
let _authConnection: Connection | undefined = undefined;

export const QueueConnected = () => _queueConnection?.IsConnected() ?? false;

export const getAuthConnection = () => {
  if (!_authConnection) _authConnection = new Connection(false, env.APP_ENV + '_be_auth');
  return _authConnection.connection();
};

export const getWorkerConfig = () => {
  if (!_workerConnection) _workerConnection = new Connection(false);
  return {
    connection: _workerConnection!.connection(),
    prefix: env.APP_ENV + '_build-engine'
  } as const;
};

export const getQueueConfig = () => {
  if (!_queueConnection) _queues = createQueues();
  return {
    connection: _queueConnection!.connection(),
    prefix: env.APP_ENV + '_build-engine',
    telemetry: new BullMQOtel(env.APP_ENV + '_build-engine'),
    defaultJobOptions: {
      // https://docs.bullmq.io/guide/queues/auto-removal-of-jobs#keep-a-certain-number-of-jobs
      removeOnComplete: {
        // 2 weeks
        age: 2 * 7 * 24 * 60 * 60,
        count: 1000
      },
      removeOnFail: {
        // 2 weeks
        age: 2 * 7 * 24 * 60 * 60,
        count: 2000
      }
    }
  } as const;
};
let _queues: ReturnType<typeof createQueues> | undefined = undefined;

function createQueues() {
  if (!_queueConnection) {
    _queueConnection = new Connection(true);
  }
  /** Queue for Product Builds */
  const Builds = new Queue<BuildJob>(QueueName.Builds, getQueueConfig());
  /** Queue for S3 jobs */
  const S3 = new Queue<S3Job>(QueueName.S3, getQueueConfig());
  /** Queue for Product Publishing  */
  const Releases = new Queue<PublishJob>(QueueName.Releases, getQueueConfig());
  /** Queue for jobs that poll BuildEngine, such as checking the status of a build */
  const Polling = new Queue<PollJob>(QueueName.Polling, getQueueConfig());
  /** Queue for jobs that run on startup, such as creating the CodeBuild project */
  const SystemStartup = new Queue<StartupJob>(QueueName.System_Startup, getQueueConfig());
  /** Queue for default recurring jobs such as refreshing the cached AppVersions */
  const SystemRecurring = new Queue<RecurringJob>(QueueName.System_Recurring, getQueueConfig());
  return {
    Builds,
    S3,
    Releases,
    Polling,
    SystemStartup,
    SystemRecurring
  };
}
export function getQueues() {
  if (!_queues) {
    _queues = createQueues();
  }
  return _queues;
}

export async function closeAllQueues() {
  if (_queues) {
    await Promise.all([
      _queues.Builds.close(),
      _queues.S3.close(),
      _queues.Releases.close(),
      _queues.Polling.close(),
      _queues.SystemStartup.close(),
      _queues.SystemRecurring.close()
    ]);
    _queues = undefined;
  }
}

export async function closeAllConnections() {
  await closeAllQueues();
  if (_workerConnection) {
    await _workerConnection.close();
    _workerConnection = undefined;
  }
  if (_queueConnection) {
    await _queueConnection.close();
    _queueConnection = undefined;
  }
  if (_authConnection) {
    await _authConnection.close();
    _authConnection = undefined;
  }
}
