import { clsx, type ClassValue } from "clsx";

/**
 * Composes conditional class names. Thin wrapper over clsx so components have
 * a single import for class composition.
 */
export function cn(...inputs: ClassValue[]): string {
  return clsx(inputs);
}

/**
 * Formats an ISO 8601 date string as a short, human-readable date. Returns an
 * empty string for missing or unparseable input.
 */
export function formatDate(iso: string): string {
  if (!iso) {
    return "";
  }
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) {
    return "";
  }
  return new Intl.DateTimeFormat("en", {
    year: "numeric",
    month: "short",
    day: "numeric",
  }).format(date);
}
