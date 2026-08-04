import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';
import { roleGuard } from './core/guards/role.guard';
import { Login } from './features/login/login';
import { Shell } from './core/layout/shell';

export const routes: Routes = [
  { path: 'login', component: Login },
  {
    path: '',
    component: Shell,
    canActivate: [authGuard],
    canActivateChild: [authGuard],
    children: [
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
      {
        path: 'dashboard',
        loadComponent: () => import('./features/dashboard/dashboard').then((m) => m.Dashboard),
      },

      {
        path: 'contacts',
        loadComponent: () =>
          import('./features/contacts/contacts-list/contacts-list').then((m) => m.ContactsList),
      },
      {
        path: 'contacts/pipeline',
        loadComponent: () =>
          import('./features/contacts/pipeline-board/pipeline-board').then((m) => m.PipelineBoard),
      },
      {
        path: 'contacts/:id',
        loadComponent: () =>
          import('./features/contacts/contact-profile/contact-profile').then(
            (m) => m.ContactProfile
          ),
      },

      {
        path: 'leads/finder',
        loadComponent: () =>
          import('./features/lead-finder/lead-finder').then((m) => m.LeadFinder),
      },
      {
        path: 'leads/queue',
        loadComponent: () => import('./features/lead-queue/lead-queue').then((m) => m.LeadQueue),
      },

      {
        path: 'sequences',
        loadComponent: () =>
          import('./features/sequences/sequence-builder').then((m) => m.SequenceBuilder),
      },
      {
        path: 'campaigns',
        loadComponent: () => import('./features/campaigns/campaigns').then((m) => m.Campaigns),
      },
      {
        path: 'replies',
        loadComponent: () => import('./features/replies/replies-inbox').then((m) => m.RepliesInbox),
      },
      {
        path: 'actions-log',
        loadComponent: () => import('./features/actions-log/actions-log').then((m) => m.ActionsLog),
      },
      {
        path: 'analytics',
        loadComponent: () => import('./features/analytics/analytics').then((m) => m.Analytics),
      },
      {
        path: 'settings',
        loadComponent: () => import('./features/settings/settings').then((m) => m.Settings),
        canActivate: [roleGuard],
        data: { roles: ['admin'] },
      },
    ],
  },
  { path: '**', redirectTo: 'dashboard' },
];
