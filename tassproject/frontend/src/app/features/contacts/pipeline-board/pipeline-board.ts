import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { ContactService } from '../../../core/services/contact.service';
import { Contact, ContactStage, CONTACT_STAGES } from '../../../core/models/contact.model';

@Component({
  selector: 'app-pipeline-board',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './pipeline-board.html',
  styleUrl: './pipeline-board.css',
})
export class PipelineBoard implements OnInit {
  stages = CONTACT_STAGES;
  loading = signal(false);
  columns = signal<Record<ContactStage, Contact[]>>(this.emptyColumns());

  private draggingId: number | null = null;

  constructor(private contactsApi: ContactService) {}

  ngOnInit() {
    this.load();
  }

  private emptyColumns(): Record<ContactStage, Contact[]> {
    return {
      lead_found: [],
      approved: [],
      contacted: [],
      replied: [],
      interested: [],
      won: [],
      lost: [],
    };
  }

  load() {
    this.loading.set(true);
    // Pull a large page per stage-agnostic fetch; for a real 100k-contact
    // scale you'd paginate per column server-side, but this is fine for MVP.
    this.contactsApi.list({ per_page: 100 }).subscribe({
      next: (res) => {
        const grouped = this.emptyColumns();
        for (const contact of res.data) {
          grouped[contact.stage]?.push(contact);
        }
        this.columns.set(grouped);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  onDragStart(contact: Contact) {
    this.draggingId = contact.id;
  }

  onDragOver(event: DragEvent) {
    event.preventDefault();
  }

  onDrop(event: DragEvent, targetStage: ContactStage) {
    event.preventDefault();
    if (this.draggingId === null) return;

    const current = this.columns();
    let moved: Contact | undefined;
    const next = this.emptyColumns();

    for (const stage of Object.keys(current) as ContactStage[]) {
      for (const c of current[stage]) {
        if (c.id === this.draggingId) {
          moved = { ...c, stage: targetStage };
        } else {
          next[stage].push(c);
        }
      }
    }

    if (!moved) {
      this.draggingId = null;
      return;
    }

    next[targetStage].push(moved);
    this.columns.set(next);
    this.draggingId = null;

    this.contactsApi.updateStage(moved.id, targetStage).subscribe({
      error: () => this.load(), // revert to server truth on failure
    });
  }
}
