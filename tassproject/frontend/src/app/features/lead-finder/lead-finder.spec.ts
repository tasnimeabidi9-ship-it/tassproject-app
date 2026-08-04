import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { LeadFinder } from './lead-finder';
import { LeadService } from '../../core/services/lead.service';
import { SettingsService } from '../../core/services/settings.service';
import { IntegrationStatus } from '../../core/models/settings.model';

const HUNTER_MOCK: IntegrationStatus = {
  configured: false,
  label: 'Email finder (Hunter.io)',
  detail: 'Mock mode — demo emails generated',
};

describe('LeadFinder', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [LeadFinder],
      providers: [
        {
          provide: LeadService,
          useValue: {
            listProfiles: () => of([]),
            recentJobs: () => of([]),
            createProfile: () => of({}),
            deleteProfile: () => of({}),
            startScrape: () => of({}),
          },
        },
        {
          provide: SettingsService,
          useValue: {
            integrations: () =>
              of<Record<string, IntegrationStatus>>({ hunter: HUNTER_MOCK }),
          },
        },
      ],
    }).compileComponents();
  });

  it('should create', () => {
    const fixture = TestBed.createComponent(LeadFinder);
    expect(fixture.componentInstance).toBeTruthy();
    fixture.destroy();
  });

  it('loads and exposes the hunter integration status', () => {
    const fixture = TestBed.createComponent(LeadFinder);
    fixture.detectChanges();

    expect(fixture.componentInstance.hunterStatus()).toEqual(HUNTER_MOCK);
    fixture.destroy();
  });

  it('returns null when integrations are unavailable', () => {
    const fixture = TestBed.createComponent(LeadFinder);
    fixture.detectChanges();
    fixture.componentInstance.integrations.set(null);

    expect(fixture.componentInstance.hunterStatus()).toBeNull();
    fixture.destroy();
  });

  it('builds status CSS classes', () => {
    const fixture = TestBed.createComponent(LeadFinder);
    const status = fixture.componentInstance.statusClass('completed');
    expect(status).toBe('status status-completed');
    fixture.destroy();
  });
});
