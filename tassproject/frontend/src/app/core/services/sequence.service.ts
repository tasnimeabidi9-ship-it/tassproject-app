import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Campaign, Sequence } from '../models/sequence.model';

@Injectable({ providedIn: 'root' })
export class SequenceService {
  private base = environment.apiUrl;

  constructor(private http: HttpClient) {}

  listSequences(): Observable<Sequence[]> {
    return this.http.get<Sequence[]>(`${this.base}/sequences`);
  }

  createSequence(payload: Partial<Sequence>): Observable<Sequence> {
    return this.http.post<Sequence>(`${this.base}/sequences`, payload);
  }

  updateSequence(id: number, payload: Partial<Sequence>): Observable<Sequence> {
    return this.http.patch<Sequence>(`${this.base}/sequences/${id}`, payload);
  }

  deleteSequence(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.base}/sequences/${id}`);
  }

  listCampaigns(): Observable<Campaign[]> {
    return this.http.get<Campaign[]>(`${this.base}/campaigns`);
  }

  createCampaign(payload: { name: string; sequence_id: number }): Observable<Campaign> {
    return this.http.post<Campaign>(`${this.base}/campaigns`, payload);
  }

  startCampaign(id: number, contactIds?: number[]): Observable<{ message: string; campaign: Campaign }> {
    return this.http.post<{ message: string; campaign: Campaign }>(`${this.base}/campaigns/${id}/start`, {
      contact_ids: contactIds,
    });
  }

  pauseCampaign(id: number): Observable<{ message: string; campaign: Campaign }> {
    return this.http.patch<{ message: string; campaign: Campaign }>(`${this.base}/campaigns/${id}/pause`, {});
  }
}
