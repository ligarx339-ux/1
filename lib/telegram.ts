interface TelegramWebApp {
  ready(): void
  expand(): void
  close(): void
  initDataUnsafe: {
    user?: {
      id: number
      first_name: string
      last_name?: string
      username?: string
      photo_url?: string
    }
  }
  colorScheme: "light" | "dark"
  HapticFeedback?: {
    impactOccurred(style: "light" | "medium" | "heavy"): void
    notificationOccurred(type: "error" | "success" | "warning"): void
  }
  openTelegramLink(url: string): void
}

declare global {
  interface Window {
    Telegram?: {
      WebApp: TelegramWebApp
    }
  }
}

export const telegram = {
  webApp: typeof window !== "undefined" ? window.Telegram?.WebApp : null,

  init() {
    if (this.webApp) {
      this.webApp.ready()
      this.webApp.expand()
    }
  },

  getUser() {
    return this.webApp?.initDataUnsafe?.user || null
  },

  hapticFeedback(type: "light" | "medium" | "heavy" | "success" | "error" | "warning") {
    if (this.webApp?.HapticFeedback) {
      if (["light", "medium", "heavy"].includes(type)) {
        this.webApp.HapticFeedback.impactOccurred(type as "light" | "medium" | "heavy")
      } else {
        this.webApp.HapticFeedback.notificationOccurred(type as "error" | "success" | "warning")
      }
    } else if (navigator.vibrate) {
      const patterns = {
        light: [10],
        medium: [20],
        heavy: [30],
        success: [10, 50, 10],
        error: [50, 50, 50],
        warning: [20, 20, 20],
      }
      navigator.vibrate(patterns[type] || patterns.light)
    }
  },

  openLink(url: string) {
    if (this.webApp) {
      this.webApp.openTelegramLink(url)
    } else {
      window.open(url, "_blank")
    }
  },
}
