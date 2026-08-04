export type UserRole = 'admin' | 'rep' | 'readonly';

export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
  last_login?: string | null;
}

export interface LoginResponse {
  token: string;
  user: User;
}
