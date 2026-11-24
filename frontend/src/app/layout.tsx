import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "EasyCalendar",
  description: "Prise de rendez-vous en ligne, acompte par mobile money et rappels WhatsApp.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="fr">
      <body>{children}</body>
    </html>
  );
}
