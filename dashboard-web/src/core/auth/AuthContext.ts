import { createContext } from 'react';
import type { DashboardUser } from './sessionLifecycle';

export type User = DashboardUser;

export interface AuthContextType {
  user: User | null;
  token: string | null;
  login: (user: User, token: string) => void;
  logout: () => Promise<void>;
  isLoading: boolean;
  sessionError: string | null;
  retrySession: () => Promise<void>;
}

export const AuthContext = createContext<AuthContextType | undefined>(undefined);
