/**
 * Adresse de l'API Laravel (voir docs/API.md). Définie par NEXT_PUBLIC_API_URL,
 * sans slash final ; les routes sont versionnées sous /api/v1.
 */
export const API_BASE_URL = (process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000").replace(/\/+$/, "");

export function apiUrl(path: string): string {
  return `${API_BASE_URL}/api/v1/${path.replace(/^\/+/, "")}`;
}
