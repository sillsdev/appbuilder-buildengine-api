import { Queue } from 'bullmq';
import { Redis } from 'ioredis';
import type { BuildJob, PollJob, ProjectJob, PublishJob, S3Job } from './types';
import { QueueName } from './types';

class Connection {
  private conn: Redis;
  private connected: boolean;
  constructor(isQueueConnection = false) {
    this.conn = new Redis({
      host: process.env.NODE_ENV === 'development' ? 'localhost' : process.env.VALKEY_HOST,
      maxRetriesPerRequest: isQueueConnection ? undefined : null
    });
    this.connected = false;
    this.conn.on('close', () => {
      this.connected = false;
    });
    this.conn.on('connect', () => {
      this.connected = true;
    });
    this.conn.on('error', (err) => {
      this.connected = false;
      if (err.message.includes('ENOTFOUND')) {
        console.error('Fatal Valkey connection', err);
        process.exit(1);
      } else if (!err.message.includes('ECONNREFUSED')) {
        console.error('Valkey connection error', err);
      }
    });
    setInterval(() => {
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
            }
          });
      }
    }, 10000).unref(); // Check every 10 seconds
  }
  public IsConnected() {
    return this.connected;
  }

  public connection() {
    return this.conn;
  }
}

let _workerConnection: Connection | undefined = undefined;
let _queueConnection: Connection | undefined = undefined;

export const QueueConnected = () => _queueConnection?.IsConnected() ?? false;

export const getWorkerConfig = () => {
  if (!_workerConnection) _workerConnection = new Connection(false);
  return {
    connection: _workerConnection!.connection(),
    prefix: 'build-engine'
  } as const;
};

export const getQueueConfig = () => {
  if (!_queueConnection) _queues = createQueues();
  return {
    connection: _queueConnection!.connection(),
    prefix: 'build-engine'
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
  /** Queue for miscellaneous jobs in BuildEngine such as Product and Project Creation */
  const Projects = new Queue<ProjectJob>(QueueName.Projects, getQueueConfig());
  /** Queue for Product Publishing  */
  const Publishing = new Queue<PublishJob>(QueueName.Publishing, getQueueConfig());
  /** Queue for jobs that poll BuildEngine, such as checking the status of a build */
  const Polling = new Queue<PollJob>(QueueName.Polling, getQueueConfig());
  return {
    Builds,
    S3,
    Projects,
    Publishing,
    Polling
  };
}
export function getQueues() {
  if (!_queues) {
    _queues = createQueues();
  }
  return _queues;
}
