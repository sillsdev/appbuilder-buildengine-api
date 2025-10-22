import { Prisma, PrismaClient } from '@prisma/client';

export const prisma = new PrismaClient();

class ConnectionChecker {
  private connected: boolean;
  constructor() {
    this.connected = false;
    this.checkConnection();
    setInterval(async () => this.checkConnection(), 10000).unref(); // Check every 10 seconds
  }
  private async checkConnection() {
    try {
      await prisma.$queryRaw`SELECT 1`;
      if (!this.connected) {
        this.connected = true;
      }
    } catch (e) {
      if (
        e instanceof Prisma.PrismaClientKnownRequestError ||
        e instanceof Prisma.PrismaClientRustPanicError ||
        e instanceof Prisma.PrismaClientInitializationError
      ) {
        // As best as I can tell, the only types of PrismaClientKnownRequestError that
        // should be thrown by the above query would involve the database being unreachable.
        if (this.connected) {
          this.connected = false;
          console.log('Error checking database connection:', e);
        }
      } else {
        throw e;
      }
    }
  }
  public IsConnected() {
    return this.connected;
  }
}

let conn: ConnectionChecker | null = null;

/** Main database is up */
export const DatabaseConnected = () => {
  if (!conn) {
    // If conn is not initialized, we create a new one
    // This is to ensure that the connection checker is only created once
    conn = new ConnectionChecker();
  }
  return conn.IsConnected();
};
