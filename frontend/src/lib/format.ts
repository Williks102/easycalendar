/** Fuseau d'affichage : les dates sont stockées en UTC, affichées à Abidjan (CLAUDE.md). */
export const DISPLAY_TIMEZONE = "Africa/Abidjan";

/** Montant en FCFA : toujours un entier, jamais de décimales (ex. 10000 -> « 10 000 FCFA »). */
export function formatXof(amount: number): string {
  if (!Number.isInteger(amount)) {
    throw new RangeError("Un montant en FCFA doit être un entier.");
  }
  const digits = new Intl.NumberFormat("fr-FR", { maximumFractionDigits: 0 }).format(amount);
  // Espace insécable fine (U+202F) -> espace insécable ordinaire, plus sûre à l'affichage.
  return `${digits.replace(/ /g, " ")} FCFA`;
}

/** Date et heure d'un instant UTC (ISO 8601) affichées à Abidjan (ex. « 2 décembre 2026 à 09:30 »). */
export function formatDateTime(isoUtc: string): string {
  return new Intl.DateTimeFormat("fr-FR", {
    timeZone: DISPLAY_TIMEZONE,
    dateStyle: "long",
    timeStyle: "short",
  }).format(new Date(isoUtc));
}
