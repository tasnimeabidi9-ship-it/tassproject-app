import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { PaginatedReplies, Reply } from '../models/reply.model';

@Injectable({ providedIn: 'root' })
export class ReplyService {
  private base = environment.apiUrl;

  constructor(private http: HttpClient) {}

  list(unreadOnly = false): Observable<PaginatedReplies> {
    let params = new HttpParams();
    if (unreadOnly) params = params.set('unread_only', 'true');
    return this.http.get<PaginatedReplies>(`${this.base}/replies`, { params });
  }

  markRead(id: number): Observable<Reply> {
    return this.http.patch<Reply>(`${this.base}/replies/${id}/read`, {});
  }

  respond(id: number, body: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.base}/replies/${id}/respond`, { body });
  }

  simulate(contactId: number, body: string): Observable<Reply> {
    return this.http.post<Reply>(`${this.base}/replies/simulate`, { contact_id: contactId, body });
  }
}
