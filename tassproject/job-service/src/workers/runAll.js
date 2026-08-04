import { config } from '../config.js';
import { startEmailWorker } from './emailWorker.js';
import { startSmsWorker } from './smsWorker.js';

console.log(`Job workers starting. emailMock=${config.emailMock}, smsMock=${config.smsMock}`);

startEmailWorker();
startSmsWorker();

console.log('Workers listening on queues: email-outreach, sms-outreach');
