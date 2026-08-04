import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import {
  Contact,
  ContactFilters,
  ContactProfileResponse,
  ImportResult,
  PaginatedResponse,
} from '../models/contact.model';

@Injectable({ providedIn: 'root' })
export class ContactService {
  private base = `${environment.apiUrl}/contacts`;

  constructor(private http: HttpClient) {}

  list(filters: ContactFilters): Observable<PaginatedResponse<Contact>> {
    let params = new HttpParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params = params.set(key, String(value));
      }
    });
    return this.http.get<PaginatedResponse<Contact>>(this.base, { params });
  }

  get(id: number): Observable<ContactProfileResponse> {
    return this.http.get<ContactProfileResponse>(`${this.base}/${id}`);
  }

  create(payload: Partial<Contact>): Observable<Contact> {
    return this.http.post<Contact>(this.base, payload);
  }

  update(id: number, payload: Partial<Contact>): Observable<Contact> {
    return this.http.patch<Contact>(`${this.base}/${id}`, payload);
  }

  updateStage(id: number, stage: Contact['stage']): Observable<Contact> {
    return this.http.patch<Contact>(`${this.base}/${id}/stage`, { stage });
  }

  delete(id: number): Observable<{ message: string }> {
    return this.http.delete<{ message: string }>(`${this.base}/${id}`);
  }

  import(file: File): Observable<ImportResult> {
    const formData = new FormData();
    formData.append('file', file);
    return this.http.post<ImportResult>(`${this.base}/import`, formData);
  }

  exportUrl(): string {
    return `${this.base}/export`;
  }
}
