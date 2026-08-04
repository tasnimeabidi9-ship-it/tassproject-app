export type StepChannel = 'email' | 'sms';

export interface SequenceStep {
  channel: StepChannel;
  delay_days: number;
  subject?: string;
  body: string;
}

export interface Sequence {
  id: number;
  name: string;
  steps: SequenceStep[];
  created_at: string;
  updated_at: string;
}

export type CampaignStatus = 'draft' | 'running' | 'paused' | 'completed';

export interface Campaign {
  id: number;
  name: string;
  sequence_id: number;
  sequence?: { id: number; name: string };
  status: CampaignStatus;
  started_at: string | null;
  created_at: string;
}
