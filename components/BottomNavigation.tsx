"use client"

import { Pickaxe, Rocket, Target, Wallet, Users } from "lucide-react"

interface BottomNavigationProps {
  activeSection: string
  onSectionChange: (section: string) => void
}

export const BottomNavigation = ({ activeSection, onSectionChange }: BottomNavigationProps) => {
  const navItems = [
    { id: "mining", icon: Pickaxe, label: "Mine" },
    { id: "boost", icon: Rocket, label: "Boost" },
    { id: "missions", icon: Target, label: "Tasks" },
    { id: "wallet", icon: Wallet, label: "Wallet" },
    { id: "friends", icon: Users, label: "Friends" },
  ]

  return (
    <nav className="fixed bottom-0 left-0 right-0 bg-gradient-to-t from-black via-gray-900/95 to-black/95 backdrop-blur-xl border-t-2 border-green-500/40 p-2 sm:p-3 z-50 shadow-2xl shadow-green-500/20 nav-compact">
      <div className="flex justify-around items-center gap-1 sm:gap-2">
        {navItems.map((item) => {
          const Icon = item.icon
          const isActive = activeSection === item.id

          return (
            <button
              key={item.id}
              onClick={() => onSectionChange(item.id)}
              className={`flex flex-col items-center justify-center p-2 sm:p-3 rounded-xl sm:rounded-2xl transition-all duration-300 flex-1 max-w-[70px] sm:max-w-[80px] relative group ${
                isActive
                  ? "text-green-400 bg-gradient-to-t from-green-500/30 to-green-500/10 border-2 border-green-500/60 shadow-lg shadow-green-500/40 scale-105 sm:scale-110"
                  : "text-gray-400 hover:text-green-400 hover:scale-105 hover:bg-gradient-to-t hover:from-gray-800/40 hover:to-gray-700/20"
              }`}
            >
              {isActive && (
                <div className="absolute -top-1 sm:-top-2 left-1/2 transform -translate-x-1/2 w-4 sm:w-6 h-1 sm:h-2 bg-gradient-to-r from-green-400 via-blue-500 to-purple-500 rounded-full animate-pulse shadow-lg" />
              )}
              <Icon className={`w-5 h-5 sm:w-6 sm:h-6 md:w-7 md:h-7 transition-all duration-300 ${isActive ? "scale-110 drop-shadow-lg" : ""} mb-0.5 sm:mb-1`} />
              <span className={`text-xs font-semibold transition-all duration-300 hidden sm:block ${
                isActive ? "text-green-400" : "text-gray-500"
              }`}>
                {item.label}
              </span>
            </button>
          )
        })}
      </div>
    </nav>
  )
}