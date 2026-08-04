export type Sentiment = 'Positive' | 'Interested' | 'Negative' | 'Out of Office' | 'Unsubscribe';

export interface Reply {
  id: number;
  message_id: number;
  contact_id: number;
  body: string;
  sentiment: Sentiment;
  read_by_admin: boolean;
  created_at: string;
  contact?: { id: number; name: string | null; email: string | null; company: string | null };
  message?: { id: number; channel: string; subject: string | null };
}

export interface PaginatedReplies {
  data: Reply[];
  current_page: number;
  last_page: number;
  total: number;
}
