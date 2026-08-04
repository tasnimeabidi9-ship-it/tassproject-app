import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { ContactService } from '../../../core/services/contact.service';
import { Contact, ContactFilters, CONTACT_STAGES, ImportResult } from '../../../core/models/contact.model';

@Component({
  selector: 'app-contacts-list',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './contacts-list.html',
  styleUrl: './contacts-list.css',
})
export class ContactsList implements OnInit {
  stages = CONTACT_STAGES;

  contacts = signal<Contact[]>([]);
  loading = signal(false);
  currentPage = signal(1);
  lastPage = signal(1);
  total = signal(0);

  search = '';
  status = '';
  stage = '';
  minScore: number | null = null;

  importing = signal(false);
  importResult = signal<ImportResult | null>(null);

  constructor(private contactsApi: ContactService, private http: HttpClient) {}

  ngOnInit() {
    this.fetch();
  }

  fetch(page = 1) {
    this.loading.set(true);
    const filters: ContactFilters = {
      search: this.search || undefined,
      status: this.status || undefined,
      stage: (this.stage as ContactFilters['stage']) || undefined,
      min_score: this.minScore ?? undefined,
      page,
      per_page: 25,
    };

    this.contactsApi.list(filters).subscribe({
      next: (res) => {
        this.contacts.set(res.data);
        this.currentPage.set(res.current_page);
        this.lastPage.set(res.last_page);
        this.total.set(res.total);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  applyFilters() {
    this.fetch(1);
  }

  clearFilters() {
    this.search = '';
    this.status = '';
    this.stage = '';
    this.minScore = null;
    this.fetch(1);
  }

  goToPage(page: number) {
    if (page < 1 || page > this.lastPage()) return;
    this.fetch(page);
  }

  onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    this.importing.set(true);
    this.importResult.set(null);

    this.contactsApi.import(file).subscribe({
      next: (result) => {
        this.importing.set(false);
        this.importResult.set(result);
        this.fetch(this.currentPage());
        input.value = '';
      },
      error: () => {
        this.importing.set(false);
        input.value = '';
      },
    });
  }

  exportCsv() {
    this.http.get(this.contactsApi.exportUrl(), { responseType: 'blob' }).subscribe((blob) => {
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `contacts_export_${new Date().toISOString().slice(0, 10)}.csv`;
      a.click();
      window.URL.revokeObjectURL(url);
    });
  }

  stageLabel(value: string): string {
    return this.stages.find((s) => s.value === value)?.label ?? value;
  }
}
