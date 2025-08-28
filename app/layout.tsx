import type React from "react"
import type { Metadata } from "next"
import { Inter, Orbitron } from "next/font/google"
import "./globals.css"
import { Toaster } from "@/components/ui/toaster"

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-inter",
})

const orbitron = Orbitron({
  subsets: ["latin"],
  variable: "--font-orbitron",
})

export const metadata: Metadata = {
  title: "UC Coin Ultra - Advanced Gaming Platform",
  description:
    "The ultimate gaming platform for earning UC coins through tapping, missions, and referrals. Join now and start earning!",
  keywords: "UC Coin, Gaming, Crypto, Tap to Earn, PUBG Mobile, Telegram Bot",
  authors: [{ name: "UC Coin Team" }],
  robots: "index, follow",
  openGraph: {
    title: "UC Coin Ultra - Gaming Platform",
    description: "Earn UC coins through tapping, missions, and referrals. The ultimate gaming experience!",
    type: "website",
    images: [
      {
        url: "/background.png",
        width: 1200,
        height: 630,
        alt: "UC Coin Ultra",
      },
    ],
  },
  twitter: {
    card: "summary_large_image",
    title: "UC Coin Ultra - Advanced Gaming Platform",
    description: "Earn UC coins through tapping, missions, and referrals!",
    images: ["/background.png"],
  },
  themeColor: "#4CAF50",
  manifest: "/manifest.json",
  appleWebApp: {
    capable: true,
    statusBarStyle: "black-translucent",
    title: "UC Coin Ultra",
  },
    generator: 'v0.dev'
}

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  return (
    <html lang="en" className={`${inter.variable} ${orbitron.variable}`}>
      <head>
        <link rel="icon" type="image/png" href="/background.png" />
        <script src="https://telegram.org/js/telegram-web-app.js" async />
      </head>
      <body className={`${inter.className} antialiased`}>
        {children}
        <Toaster />
      </body>
    </html>
  )
}
