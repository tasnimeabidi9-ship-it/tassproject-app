export type ContactStage =
  | 'lead_found'
  | 'approved'
  | 'contacted'
  | 'replied'
  | 'interested'
  | 'won'
  | 'lost';

export const CONTACT_STAGES: { value: ContactStage; label: string }[] = [
  { value: 'lead_found', label: 'Lead Found' },
  { value: 'approved', label: 'Approved' },
  { value: 'contacted', label: 'Contacted' },
  { value: 'replied', label: 'Replied' },
  { value: 'interested', label: 'Interested' },
  { value: 'won', label: 'Won' },
  { value: 'lost', label: 'Lost' },
];

export interface Contact {
  id: number;
  name: string | null;
  email: string | null;
  phone: string | null;
  company: string | null;
  website: string | null;
  domain: string | null;
  source_url: string | null;
  match_score: number;
  stage: ContactStage;
  status: string;
  assigned_to: number | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

export interface PaginatedResponse<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ContactFilters {
  search?: string;
  status?: string;
  stage?: ContactStage | '';
  min_score?: number;
  page?: number;
  per_page?: number;
}

export interface TimelineEntry {
  type: 'message' | 'reply' | 'task';
  timestamp: string;
  data: Record<string, unknown>;
}

export interface ContactProfileResponse {
  contact: Contact;
  timeline: TimelineEntry[];
}

export interface ImportResult {
  imported: number;
  skipped: number;
  errors: string[];
}
