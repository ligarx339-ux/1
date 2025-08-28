"use client"

import type { User } from "@/types"
import { X, Volume2, VolumeX, Smartphone, Bell, BellOff, Palette, Shield, Info } from "lucide-react"

interface SettingsModalProps {
  isOpen: boolean
  onClose: () => void
  user: User
  onUpdateSettings: (settings: Partial<User["settings"]>) => void
}

export const SettingsModal = ({ isOpen, onClose, user, onUpdateSettings }: SettingsModalProps) => {
  if (!isOpen) return null

  const toggleSetting = (setting: keyof User["settings"]) => {
    onUpdateSettings({
      [setting]: !user.settings[setting],
    })
  }

  const settings = [
    {
      id: "sound" as const,
      title: "Sound Effects",
      description: "Enable tap sounds and audio feedback",
      icon: user.settings.sound ? Volume2 : VolumeX,
      enabled: user.settings.sound,
      color: "from-blue-400 to-cyan-500",
    },
    {
      id: "vibration" as const,
      title: "Haptic Feedback",
      description: "Feel vibrations when tapping",
      icon: Smartphone,
      enabled: user.settings.vibration,
      color: "from-purple-400 to-pink-500",
    },
    {
      id: "notifications" as const,
      title: "Push Notifications",
      description: "Get notified about rewards and updates",
      icon: user.settings.notifications ? Bell : BellOff,
      enabled: user.settings.notifications,
      color: "from-green-400 to-emerald-500",
    },
  ]

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
      <div className="w-full max-w-sm bg-gradient-to-br from-gray-900/95 to-black/95 backdrop-blur-xl border-2 border-green-500/30 rounded-2xl shadow-2xl shadow-green-500/20 overflow-hidden">
        {/* Header */}
        <div className="relative bg-gradient-to-r from-green-500/10 to-blue-500/10 p-4 border-b border-gray-700/30">
          <div className="absolute inset-0 bg-gradient-to-r from-green-400/5 to-blue-400/5" />
          <div className="relative flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-gradient-to-br from-green-400 to-blue-500 rounded-xl flex items-center justify-center text-lg shadow-lg shadow-green-500/30">
                ⚙️
              </div>
              <div>
                <h2 className="text-lg font-bold text-white font-display">Settings</h2>
                <p className="text-xs text-gray-300">Customize experience</p>
              </div>
            </div>
            <button
              onClick={onClose}
              className="w-8 h-8 bg-gray-700/20 hover:bg-red-500/20 border border-gray-700/30 hover:border-red-500/50 rounded-xl flex items-center justify-center text-gray-400 hover:text-red-400 transition-all duration-300"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        </div>

        {/* Settings List */}
        <div className="p-4 space-y-3">
          {settings.map((setting) => {
            const Icon = setting.icon

            return (
              <div
                key={setting.id}
                className="group bg-gradient-to-r from-black/20 to-gray-900/20 backdrop-blur-md rounded-xl p-3 border border-gray-700/20 hover:border-green-500/30 transition-all duration-300"
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3 flex-1">
                    <div className={`w-10 h-10 bg-gradient-to-br ${setting.color} rounded-xl flex items-center justify-center text-white shadow-lg`}>
                      <Icon className="w-5 h-5" />
                    </div>
                    <div className="flex-1">
                      <div className="text-sm font-bold text-white">{setting.title}</div>
                      <div className="text-xs text-gray-400">{setting.description}</div>
                    </div>
                  </div>

                  {/* Toggle Switch */}
                  <button
                    onClick={() => toggleSetting(setting.id)}
                    className={`relative w-12 h-6 rounded-full border-2 transition-all duration-300 focus:outline-none ${
                      setting.enabled
                        ? "bg-gradient-to-r from-green-400 to-blue-500 border-green-500/50 shadow-lg shadow-green-500/30"
                        : "bg-gray-700/50 border-gray-600/50"
                    }`}
                  >
                    <div
                      className={`absolute top-0.5 w-5 h-5 bg-white rounded-full shadow-lg transition-all duration-300 flex items-center justify-center ${
                        setting.enabled ? "left-6" : "left-0.5"
                      }`}
                    >
                      <div className={`w-2 h-2 rounded-full transition-colors duration-300 ${
                        setting.enabled ? "bg-green-500" : "bg-gray-400"
                      }`} />
                    </div>
                  </button>
                </div>
              </div>
            )
          })}
        </div>

        {/* Footer Info */}

        <div className="px-4 pb-4">
          <div className="text-center">
            <p className="text-xs text-gray-500">UC Coin Ultra v1.0.0</p>
          </div>
        </div>
      </div>
    </div>
  )
}