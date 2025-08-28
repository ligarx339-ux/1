import { type ClassValue, clsx } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export const debounce = <T extends (...args: any[]) => any>(
  func: T,
  wait: number,
): ((...args: Parameters<T>) => void) => {
  let timeout: NodeJS.Timeout
  return (...args: Parameters<T>) => {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

export const generateUUID = (): string => {
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0
    return (c === "x" ? r : (r & 0x3) | 0x8).toString(16)
  })
}

export const getUrlParameter = (name: string): string | null => {
  if (typeof window === "undefined") return null
  const urlParams = new URLSearchParams(window.location.search)
  return urlParams.get(name)
}

export const clearUrlParameters = () => {
  // URL parametrlarini tozalamaslik - foydalanuvchi so'rovi bo'yicha
  // if (typeof window === "undefined") return
  // const url = new URL(window.location.href)
  // url.search = ""
  // window.history.replaceState({}, document.title, url.toString())
}

export const parseReferralFromUrl = (): string | null => {
  const refParam = getUrlParameter("ref")
  const startParam = getUrlParameter("start")

  if (refParam) return refParam
  if (startParam && startParam.startsWith("ref_")) {
    return startParam.replace("ref_", "")
  }

  return null
}

export const parseRefAuthFromUrl = (): string | null => {
  return getUrlParameter("refauth")
}