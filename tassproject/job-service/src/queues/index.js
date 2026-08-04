import { Queue } from 'bullmq';
import IORedis from 'ioredis';
import { config } from '../config.js';

export const connection = new IORedis(config.redisPort, config.redisHost, {
  maxRetriesPerRequest: null, // required by BullMQ
});

export const emailQueue = new Queue('email-outreach', { connection });
export const smsQueue = new Queue('sms-outreach', { connection });

export function queueForChannel(channel) {
  return channel === 'sms' ? smsQueue : emailQueue;
}
