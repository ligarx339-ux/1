"use client"

import type { User } from "@/types"
import { gameLogic } from "@/lib/game-logic"
import { Settings } from "lucide-react"
import Image from "next/image"

interface HeaderProps {
  user: User
  onOpenSettings: () => void
}

export const Header = ({ user, onOpenSettings }: HeaderProps) => {
  const { level, currentXP, xpForNext } = gameLogic.calculateLevel(user.xp)
  const xpProgress = (currentXP / xpForNext) * 100

  const displayName = user.firstName + (user.lastName ? ` ${user.lastName}` : "")
  
  // Use Firebase avatar or generate one based on name
  const avatarUrl = user.avatarUrl || 
    `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName.replace(/\s+/g, "+"))}&background=4CAF50&color=fff&size=140`

  return (
    <header className="bg-gradient-to-r from-black/50 to-gray-900/50 backdrop-blur-lg border-2 border-green-500/40 rounded-2xl p-2 sm:p-4 mt-1 sm:mt-3 shadow-xl shadow-green-500/20 mx-1 sm:mx-2 header-compact">
      <div className="flex items-center gap-3 sm:gap-4 mb-3 sm:mb-4">
        {/* Avatar */}
        <div className="relative group">
          <Image
            src={avatarUrl || "/placeholder.svg"}
            alt="User Avatar"
            width={40}
            height={40}
            className="w-8 h-8 sm:w-12 sm:h-12 md:w-14 md:h-14 rounded-full border-2 sm:border-3 border-green-500 shadow-xl shadow-green-500/60 transition-all duration-300 group-hover:scale-110 group-hover:shadow-2xl group-hover:shadow-green-500/80"
          />
          <div className="absolute -bottom-0.5 -right-0.5 w-3 h-3 sm:w-4 sm:h-4 bg-gradient-to-r from-green-400 to-emerald-500 rounded-full border-2 border-black animate-pulse shadow-lg" />
        </div>

        {/* User Info */}
        <div className="flex-1 min-w-0">
          <h2 className="text-sm sm:text-base md:text-lg font-bold text-white font-display truncate bg-gradient-to-r from-white to-gray-200 bg-clip-text">{displayName}</h2>
          {/* Displaying balance here as requested */}
          <div className="flex items-center gap-2 mt-1">
            <span className="text-lg sm:text-xl md:text-2xl text-white font-bold bg-gradient-to-r from-yellow-400 to-orange-500 bg-clip-text text-transparent">{gameLogic.formatNumber(user.balance)}</span>
            <p className="text-sm sm:text-base md:text-lg text-green-400 font-bold animate-pulse">DRX</p>
          </div>
        </div>

        {/* Settings Button */}
        <button
          onClick={onOpenSettings}
          className="w-8 h-8 sm:w-12 sm:h-12 md:w-14 md:h-14 bg-gradient-to-br from-blue-500/20 to-purple-500/20 border-2 border-blue-500/40 rounded-lg sm:rounded-xl flex items-center justify-center text-blue-400 hover:bg-gradient-to-br hover:from-blue-500 hover:to-purple-500 hover:text-white transition-all duration-300 hover:scale-110 shadow-lg hover:shadow-xl hover:shadow-blue-500/50"
        >
          <Settings className="w-3 h-3 sm:w-5 sm:h-5 md:w-6 md:h-6" />
        </button>
      </div>

      {/* XP Progress */}
      <div>
        <div className="flex justify-between text-xs text-gray-300 mb-1 sm:mb-2">
          <span className="font-semibold bg-gradient-to-r from-blue-400 to-purple-500 bg-clip-text text-transparent">⭐ Level {level}</span>
          <span className="font-semibold">
            {gameLogic.formatNumber(currentXP)} / {gameLogic.formatNumber(xpForNext)} XP
          </span>
        </div>
        <div className="w-full h-2 sm:h-3 md:h-4 bg-gradient-to-r from-gray-800 to-gray-700 rounded-full overflow-hidden border border-gray-600/50 shadow-inner">
          <div
            className="h-full bg-gradient-to-r from-blue-400 via-purple-500 to-pink-500 rounded-full transition-all duration-1000 relative overflow-hidden shadow-lg"
            style={{ width: `${xpProgress}%` }}
          >
            <div className="absolute inset-0 bg-gradient-to-r from-white/30 to-transparent animate-pulse" />
            <div className="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent animate-shimmer" />
          </div>
        </div>
      </div>
    </header>
  )
}