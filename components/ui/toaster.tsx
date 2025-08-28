"use client"

import { useToast } from "@/hooks/use-toast"
import {
  Toast,
  ToastClose,
  ToastDescription,
  ToastProvider,
  ToastTitle,
  ToastViewport,
} from "@/components/ui/toast"

export function Toaster() {
  const { toasts } = useToast()

  return (
    <ToastProvider>
      {toasts.map(function ({ id, title, description, action, ...props }) {
        return (
          <Toast key={id} {...props} className="bg-gray-900/95 backdrop-blur-md border-green-500/30 shadow-xl shadow-green-500/20">
            <div className="grid gap-1">
              {title && <ToastTitle className="text-white font-bold">{title}</ToastTitle>}
              {description && (
                <ToastDescription className="text-gray-300">{description}</ToastDescription>
              )}
            </div>
            {action}
            <ToastClose className="text-gray-400 hover:text-white" />
          </Toast>
        )
      })}
      <ToastViewport className="fixed top-4 right-4 z-[100] flex max-h-screen w-full flex-col-reverse p-4 sm:bottom-auto sm:right-4 sm:top-4 sm:flex-col md:max-w-[420px]" />
    </ToastProvider>
  )
}