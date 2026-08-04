export interface DashboardData {
  leads_found_today: number;
  emails_sent_today: number;
  sms_sent_today: number;
  reply_rate_percent: number;
  active_sequences: number;
  alerts: {
    failed_messages: number;
    leads_without_email: number;
    unread_replies: number;
  };
  active_campaigns: Array<{
    id: number;
    name: string;
    status: string;
    started_at: string | null;
    sent_count: number;
    scheduled_count: number;
  }>;
}

export interface AnalyticsOverview {
  kpis: {
    total_leads: number;
    leads_found_today: number;
    emails_sent: number;
    sms_sent: number;
    replies_count: number;
    reply_rate_percent: number;
    active_campaigns: number;
    won_deals: number;
    conversion_rate_percent: number;
  };
  pipeline: Array<{ stage: string; count: number }>;
  channel_split: {
    email: { sent: number; replied: number };
    sms: { sent: number; replied: number };
  };
  status_breakdown: Record<string, number>;
  sentiment: Record<string, number>;
  timeline: Array<{ date: string; sent: number; opened: number; replied: number }>;
  top_companies: Array<{ company: string; count: number }>;
  best_send_time: { hour: number | null; day: string | null };
  campaigns: Array<{
    id: number;
    name: string;
    status: string;
    sent: number;
    replied: number;
    reply_rate_percent: number;
  }>;
}

export interface CampaignAnalytics {
  campaign: { id: number; name: string; status: string };
  contacts_enrolled: number;
  messages: {
    total: number;
    sent: number;
    failed: number;
    scheduled: number;
    cancelled: number;
  };
  replies_count: number;
  reply_rate_percent: number;
  stage_breakdown: Record<string, number>;
}

export interface AiActionLogEntry {
  id: number;
  contact_id: number;
  message_id: number | null;
  action_type: string;
  channel: string | null;
  content_preview: string | null;
  status: string;
  scheduled_at: string | null;
  executed_at: string | null;
  created_at: string;
  contact?: { id: number; name: string | null; email: string | null };
  message?: { id: number; campaign_id: number | null; channel: string; campaign?: { id: number; name: string } };
}
