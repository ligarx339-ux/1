import type { Config } from "tailwindcss"

const config: Config = {
  darkMode: ["class"],
  content: [
    "./pages/**/*.{ts,tsx}",
    "./components/**/*.{ts,tsx}",
    "./app/**/*.{ts,tsx}",
    "./src/**/*.{ts,tsx}",
    "*.{js,ts,jsx,tsx,mdx}",
  ],
  prefix: "",
  theme: {
    container: {
      center: true,
      padding: "2rem",
      screens: {
        "2xl": "1400px",
      },
    },
    extend: {
      fontFamily: {
        sans: ["var(--font-inter)"],
        display: ["var(--font-orbitron)"], // Use for prominent titles
      },
      colors: {
        border: "hsl(var(--border))",
        input: "hsl(var(--input))",
        ring: "hsl(var(--ring))",
        background: "hsl(var(--background))",
        foreground: "hsl(var(--foreground))",
        primary: {
          DEFAULT: "hsl(var(--primary))",
          foreground: "hsl(var(--primary-foreground))",
        },
        secondary: {
          DEFAULT: "hsl(var(--secondary))",
          foreground: "hsl(var(--secondary-foreground))",
        },
        destructive: {
          DEFAULT: "hsl(var(--destructive))",
          foreground: "hsl(var(--destructive-foreground))",
        },
        muted: {
          DEFAULT: "hsl(var(--muted))",
          foreground: "hsl(var(--muted-foreground))",
        },
        accent: {
          DEFAULT: "hsl(var(--accent))",
          foreground: "hsl(var(--accent-foreground))",
        },
        popover: {
          DEFAULT: "hsl(var(--popover))",
          foreground: "hsl(var(--popover-foreground))",
        },
        card: {
          DEFAULT: "hsl(var(--card))",
          foreground: "hsl(var(--card-foreground))",
        },
      },
      borderRadius: {
        lg: "var(--radius)",
        md: "calc(var(--radius) - 2px)",
        sm: "calc(var(--radius) - 4px)",
      },
      keyframes: {
        "accordion-down": {
          from: { height: "0" },
          to: { height: "var(--radix-accordion-content-height)" },
        },
        "accordion-up": {
          from: { height: "var(--radix-accordion-content-height)" },
          to: { height: "0" },
        },
        "bounce-up": {
          "0%": {
            opacity: "1",
            transform: "translate(-50%, -50%) translateY(0) scale(1)",
          },
          "100%": {
            opacity: "0",
            transform: "translate(-50%, -50%) translateY(-80px) scale(1.2)",
          },
        },
        "bounce-up-critical": {
          "0%": {
            opacity: "1",
            transform: "translate(-50%, -50%) translateY(0) scale(1)",
          },
          "50%": {
            opacity: "1",
            transform: "translate(-50%, -50%) translateY(-40px) scale(1.3)",
          },
          "100%": {
            opacity: "0",
            transform: "translate(-50%, -50%) translateY(-100px) scale(1.5)",
          },
        },
        "bounce-up-jackpot": {
          "0%": {
            opacity: "1",
            transform: "translate(-50%, -50%) translateY(0) scale(1) rotate(0deg)",
          },
          "25%": {
            opacity: "1",
            transform: "translate(-50%, -50%) translateY(-20px) scale(1.2) rotate(5deg)",
          },
          "50%": {
            opacity: "1",
            transform: "translate(-50%, -50%) translateY(-50px) scale(1.4) rotate(-5deg)",
          },
          "75%": {
            opacity: "0.8",
            transform: "translate(-50%, -50%) translateY(-80px) scale(1.6) rotate(5deg)",
          },
          "100%": {
            opacity: "0",
            transform: "translate(-50%, -50%) translateY(-120px) scale(1.8) rotate(0deg)",
          },
        },
        bounce: {
          "0%, 20%, 53%, 80%, 100%": {
            transform: "translate3d(0, 0, 0)",
          },
          "40%, 43%": {
            transform: "translate3d(0, -30px, 0)",
          },
          "70%": {
            transform: "translate3d(0, -15px, 0)",
          },
          "90%": {
            transform: "translate3d(0, -4px, 0)",
          },
        },
        pulse: {
          "0%, 100%": {
            opacity: "1",
          },
          "50%": {
            opacity: "0.5",
          },
        },
        spin: {
          from: {
            transform: "rotate(0deg)",
          },
          to: {
            transform: "rotate(360deg)",
          },
        },
        "spin-slow": {
          from: {
            transform: "rotate(0deg)",
          },
          to: {
            transform: "rotate(360deg)",
          },
        },
        shimmer: {
          "0%": {
            "background-position": "-200% 0",
          },
          "100%": {
            "background-position": "200% 0",
          },
        },
        orbit: {
          "0%": {
            transform: "translate(-50%, -50%) rotate(0deg) translateX(120px) rotate(0deg)",
          },
          "100%": {
            transform: "translate(-50%, -50%) rotate(360deg) translateX(120px) rotate(-360deg)",
          },
        },
        "background-pulse": {
          // New keyframe
          "0%, 100%": {
            "background-color": "rgba(52, 211, 153, 0.05)",
          },
          "50%": {
            "background-color": "rgba(52, 211, 153, 0.15)",
          },
        },
        "coin-press": {
          "0%": { transform: "scale(1)" },
          "50%": { transform: "scale(0.95)" },
          "100%": { transform: "scale(1)" },
        },
        "pop-up": {
          "0%": { opacity: "0", transform: "translate(-50%, -30%) scale(0.8)" },
          "20%": { opacity: "1", transform: "translate(-50%, -50%) scale(1)" },
          "100%": { opacity: "0", transform: "translate(-50%, -100%) scale(1.2)" },
        },
      },
      animation: {
        "accordion-down": "accordion-down 0.2s ease-out",
        "accordion-up": "accordion-up 0.2s ease-out",
        "bounce-up": "bounce-up 0.5s ease-out forwards",
        "bounce-up-critical": "bounce-up-critical 0.7s ease-out forwards",
        "bounce-up-jackpot": "bounce-up-jackpot 0.9s ease-out forwards",
        bounce: "bounce 1s infinite",
        pulse: "pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite",
        spin: "spin 1s linear infinite",
        "spin-slow": "spin-slow 8s linear infinite",
        shimmer: "shimmer 2s infinite",
        orbit: "orbit 4s linear infinite",
        "background-pulse": "background-pulse 4s ease-in-out infinite",
        "coin-press": "coin-press 0.1s ease-out",
        "pop-up": "pop-up 0.5s ease-out forwards",
      },
      backgroundImage: {
        "gradient-radial": "radial-gradient(var(--tw-gradient-stops))",
        "gradient-conic": "conic-gradient(from 180deg at 50% 50%, var(--tw-gradient-stops))",
      },
      boxShadow: {
        glow: "0 0 20px rgba(52, 211, 153, 0.6)", // Updated glow color
        "glow-lg": "0 0 40px rgba(52, 211, 153, 0.8)",
      },
      screens: {
        'xs': '320px',
        'sm': '640px',
        'md': '768px',
        'lg': '1024px',
        'xl': '1280px',
        '2xl': '1536px',
        'min-h-750': { 'raw': '(min-height: 750px)' },
        'min-h-800': { 'raw': '(min-height: 800px)' },
        'min-h-900': { 'raw': '(min-height: 900px)' },
        'min-h-1000': { 'raw': '(min-height: 1000px)' },
      },
    },
  },
  plugins: [require("tailwindcss-animate")],
} satisfies Config

export default config
