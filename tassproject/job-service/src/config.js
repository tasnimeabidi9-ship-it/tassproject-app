import 'dotenv/config';

function bool(val, fallback) {
  if (val === undefined || val === '') return fallback;
  return ['1', 'true', 'yes', 'on'].includes(val.toLowerCase());
}

export const config = {
  port: parseInt(process.env.PORT || '4000', 10),

  redisHost: process.env.REDIS_HOST || '127.0.0.1',
  redisPort: parseInt(process.env.REDIS_PORT || '6379', 10),

  internalSecret: process.env.INTERNAL_SECRET || '',
  laravelApiUrl: process.env.LARAVEL_API_URL || 'http://127.0.0.1:8000/api',

  sendgridApiKey: (process.env.SENDGRID_API_KEY || '').trim(),
  sendgridFromEmail: process.env.SENDGRID_FROM_EMAIL || 'outreach@example.com',
  sendgridFromName: process.env.SENDGRID_FROM_NAME || 'Outreach',

  twilioSid: (process.env.TWILIO_ACCOUNT_SID || '').trim(),
  twilioAuthToken: (process.env.TWILIO_AUTH_TOKEN || '').trim(),
  twilioFromNumber: (process.env.TWILIO_FROM_NUMBER || '').trim(),

  openaiApiKey: (process.env.OPENAI_API_KEY || '').trim(),
  openaiModel: process.env.OPENAI_MODEL || 'gpt-4o-mini',

  get llmMock() {
    return !this.openaiApiKey;
  },

  get emailMock() {
    return !this.sendgridApiKey;
  },
  get smsMock() {
    return !this.twilioSid || !this.twilioAuthToken;
  },
};
