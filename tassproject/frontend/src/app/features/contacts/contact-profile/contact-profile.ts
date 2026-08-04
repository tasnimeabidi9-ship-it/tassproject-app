import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ContactService } from '../../../core/services/contact.service';
import { Contact, CONTACT_STAGES, TimelineEntry } from '../../../core/models/contact.model';

@Component({
  selector: 'app-contact-profile',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './contact-profile.html',
  styleUrl: './contact-profile.css',
})
export class ContactProfile implements OnInit {
  stages = CONTACT_STAGES;

  contact = signal<Contact | null>(null);
  timeline = signal<TimelineEntry[]>([]);
  loading = signal(true);
  saving = signal(false);
  notesDraft = '';

  private id!: number;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private contactsApi: ContactService
  ) {}

  ngOnInit() {
    this.id = Number(this.route.snapshot.paramMap.get('id'));
    this.load();
  }

  load() {
    this.loading.set(true);
    this.contactsApi.get(this.id).subscribe({
      next: (res) => {
        this.contact.set(res.contact);
        this.timeline.set(res.timeline);
        this.notesDraft = res.contact.notes ?? '';
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  changeStage(stage: Contact['stage']) {
    this.contactsApi.updateStage(this.id, stage).subscribe((updated) => this.contact.set(updated));
  }

  saveNotes() {
    this.saving.set(true);
    this.contactsApi.update(this.id, { notes: this.notesDraft }).subscribe({
      next: (updated) => {
        this.contact.set(updated);
        this.saving.set(false);
      },
      error: () => this.saving.set(false),
    });
  }

  deleteContact() {
    if (!confirm('Delete this contact permanently?')) return;
    this.contactsApi.delete(this.id).subscribe(() => this.router.navigate(['/contacts']));
  }

  timelineLabel(entry: TimelineEntry): string {
    const d = entry.data as any;
    switch (entry.type) {
      case 'message':
        return `${d.direction === 'out' ? 'Sent' : 'Received'} ${d.channel} — ${d.subject || d.status}`;
      case 'reply':
        return `Reply received${d.sentiment ? ' — ' + d.sentiment : ''}`;
      case 'task':
        return `Task: ${d.title}`;
      default:
        return entry.type;
    }
  }
}
