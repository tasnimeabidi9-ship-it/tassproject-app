import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { ReplyService } from '../../core/services/reply.service';
import { Reply } from '../../core/models/reply.model';
import { environment } from '../../../environments/environment';

interface ContactOption {
  id: number;
  name: string | null;
  email: string | null;
}

@Component({
  selector: 'app-replies-inbox',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './replies-inbox.html',
  styleUrl: './replies-inbox.css',
})
export class RepliesInbox implements OnInit {
  replies = signal<Reply[]>([]);
  loading = signal(false);
  unreadOnly = false;

  respondingTo = signal<number | null>(null);
  respondBody = '';
  sending = signal(false);

  showSimulate = signal(false);
  contacts = signal<ContactOption[]>([]);
  simulateContactId: number | null = null;
  simulateBody = '';
  simulating = signal(false);
  simulateError = signal<string | null>(null);

  constructor(private replyApi: ReplyService, private http: HttpClient) {}

  ngOnInit() {
    this.load();
    this.http
      .get<{ data: ContactOption[] }>(`${environment.apiUrl}/contacts?per_page=100&status=active`)
      .subscribe((res) => this.contacts.set(res.data));
  }

  load() {
    this.loading.set(true);
    this.replyApi.list(this.unreadOnly).subscribe({
      next: (res) => {
        this.replies.set(res.data);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  toggleUnread() {
    this.unreadOnly = !this.unreadOnly;
    this.load();
  }

  markRead(reply: Reply) {
    this.replyApi.markRead(reply.id).subscribe(() => this.load());
  }

  openRespond(reply: Reply) {
    this.respondingTo.set(reply.id);
    this.respondBody = '';
  }

  sendRespond(reply: Reply) {
    if (!this.respondBody.trim()) return;
    this.sending.set(true);
    this.replyApi.respond(reply.id, this.respondBody).subscribe({
      next: () => {
        this.sending.set(false);
        this.respondingTo.set(null);
        this.load();
      },
      error: () => this.sending.set(false),
    });
  }

  runSimulate() {
    if (!this.simulateContactId || !this.simulateBody.trim()) return;
    this.simulating.set(true);
    this.simulateError.set(null);
    this.replyApi.simulate(this.simulateContactId, this.simulateBody).subscribe({
      next: () => {
        this.simulating.set(false);
        this.simulateBody = '';
        this.showSimulate.set(false);
        this.load();
      },
      error: (err) => {
        this.simulating.set(false);
        this.simulateError.set(err.error?.message || 'Failed to simulate reply.');
      },
    });
  }

  sentimentClass(sentiment: string): string {
    return `sentiment sentiment-${sentiment.toLowerCase().replace(/\s+/g, '-')}`;
  }
}
