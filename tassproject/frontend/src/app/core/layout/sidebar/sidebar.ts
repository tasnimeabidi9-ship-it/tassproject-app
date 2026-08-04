import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { AuthService } from '../../services/auth.service';

interface NavItem {
  label: string;
  path: string;
  roles?: Array<'admin' | 'rep' | 'readonly'>;
}

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [CommonModule, RouterLink, RouterLinkActive],
  templateUrl: './sidebar.html',
  styleUrl: './sidebar.css',
})
export class Sidebar {
  navItems: NavItem[] = [
    { label: 'Dashboard', path: '/dashboard' },
    { label: 'Lead Finder', path: '/leads/finder' },
    { label: 'Lead Approval Queue', path: '/leads/queue' },
    { label: 'Contacts', path: '/contacts' },
    { label: 'Sequences', path: '/sequences' },
    { label: 'Campaigns', path: '/campaigns' },
    { label: 'Replies Inbox', path: '/replies' },
    { label: 'AI Actions Log', path: '/actions-log' },
    { label: 'Analytics', path: '/analytics' },
    { label: 'Settings', path: '/settings', roles: ['admin'] },
  ];

  constructor(public auth: AuthService) {}

  visibleItems() {
    const role = this.auth.role();
    return this.navItems.filter((item) => !item.roles || (role && item.roles.includes(role)));
  }
}
