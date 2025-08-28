"use client"

import { useState } from "react"
import { Sparkles, Gift, Star } from "lucide-react"

export const WelcomeModal = ({ isOpen, onClose, onClaimBonus, onSkip }) => {
  const [claiming, setClaiming] = useState(false)

  const handleClaimBonus = async () => {
    setClaiming(true)
    try {
      await onClaimBonus()
      onClose()
    } finally {
      setClaiming(false)
    }
  }

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm">
      {/* Modal Content */}
      <div className="relative z-10 w-full max-w-md mx-4 text-center bg-gradient-to-br from-gray-900/95 to-black/95 backdrop-blur-xl border-2 border-green-500/40 rounded-3xl shadow-2xl shadow-green-500/30 overflow-hidden">
        {/* Animated Background Elements */}
        <div className="absolute inset-0 overflow-hidden">
          <div className="absolute -top-10 -left-10 w-20 h-20 bg-green-400/20 rounded-full animate-pulse" />
          <div className="absolute -bottom-10 -right-10 w-32 h-32 bg-blue-400/20 rounded-full animate-pulse delay-1000" />
          <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-40 h-40 bg-gradient-to-r from-green-400/10 to-blue-400/10 rounded-full animate-spin-slow" />
        </div>

        <div className="relative z-10 p-8">
          {/* Floating Icons */}
          <div className="absolute top-4 left-4 text-yellow-400 animate-bounce">
            <Sparkles className="w-4 h-4" />
          </div>
          <div className="absolute top-4 right-4 text-green-400 animate-bounce delay-500">
            <Star className="w-4 h-4" />
          </div>
          <div className="absolute bottom-4 left-4 text-blue-400 animate-bounce delay-1000">
            <Gift className="w-4 h-4" />
          </div>

          {/* Logo */}
          <div className="relative mb-6">
            <div className="w-32 h-32 mx-auto bg-gradient-to-br from-green-400 via-blue-500 to-purple-600 rounded-full flex items-center justify-center text-6xl animate-bounce shadow-2xl shadow-green-500/60 border-4 border-white/20">
              ⛏️
            </div>
            <div className="absolute -top-2 -right-2 w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center text-lg animate-ping">
              ✨
            </div>
          </div>

          {/* Title */}
          <h1 className="text-3xl font-bold text-white mb-2 font-display">
            Welcome to 
            <span className="bg-gradient-to-r from-green-400 via-blue-500 to-purple-600 bg-clip-text text-transparent">
              {" "}DRX Mining!
            </span>
          </h1>

          {/* Subtitle */}
          <div className="flex items-center justify-center gap-2 mb-4">
            <div className="h-px bg-gradient-to-r from-transparent via-green-400 to-transparent flex-1" />
            <span className="text-green-400 text-sm font-semibold px-2">⛏️ START MINING ⛏️</span>
            <div className="h-px bg-gradient-to-r from-transparent via-green-400 to-transparent flex-1" />
          </div>

          {/* Description */}
          <p className="text-gray-200 mb-6 leading-relaxed">
            ⛏️ Mine to earn DRX coins<br />
            🎁 Complete exciting missions<br />
            👥 Invite friends for bonuses<br />
            💎 Convert DRX to various rewards!
          </p>

          {/* Welcome Bonus */}
          <div className="relative bg-gradient-to-r from-yellow-500/20 via-orange-500/20 to-red-500/20 backdrop-blur-md border-2 border-yellow-500/50 rounded-2xl p-6 mb-6 shadow-xl shadow-yellow-500/30 animate-background-pulse overflow-hidden">
            <div className="absolute inset-0 bg-gradient-to-r from-yellow-400/10 via-orange-400/10 to-red-400/10 animate-pulse" />
            <div className="relative">
              <div className="text-sm text-yellow-300 font-semibold mb-2 uppercase tracking-wide">🎁 Welcome Bonus</div>
              <div className="text-5xl font-bold text-transparent bg-gradient-to-r from-yellow-400 via-orange-500 to-red-500 bg-clip-text mb-2 animate-pulse font-display">
                100
              </div>
              <div className="text-2xl text-green-400 font-bold mb-1">DRX COINS</div>
              <div className="text-xs text-gray-300">⛏️ Start your mining journey!</div>
            </div>
            
            {/* Floating particles */}
            <div className="absolute top-2 left-4 w-2 h-2 bg-yellow-400 rounded-full animate-ping opacity-60" />
            <div className="absolute bottom-3 right-6 w-1.5 h-1.5 bg-orange-400 rounded-full animate-ping delay-500 opacity-60" />
            <div className="absolute top-1/2 right-3 w-1 h-1 bg-red-400 rounded-full animate-ping delay-1000 opacity-60" />
          </div>

          {/* Actions */}
          <div className="space-y-3">
            <button
              onClick={handleClaimBonus}
              disabled={claiming}
              className="w-full bg-gradient-to-r from-green-400 via-blue-500 to-purple-600 hover:from-green-500 hover:via-blue-600 hover:to-purple-700 disabled:from-gray-700 disabled:to-gray-800 text-white font-bold py-4 px-6 rounded-2xl transition-all duration-300 hover:scale-105 disabled:hover:scale-100 shadow-xl hover:shadow-2xl flex items-center justify-center gap-3 relative overflow-hidden group"
            >
              <div className="absolute inset-0 bg-gradient-to-r from-white/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
              <span className="text-2xl relative z-10">🎁</span>
              <span className="relative z-10 text-lg">{claiming ? "Claiming..." : "Claim Welcome Bonus"}</span>
            </button>

            <button
              onClick={onSkip}
              className="w-full bg-gray-700/20 hover:bg-gray-700/40 text-gray-300 hover:text-white font-semibold py-3 px-4 rounded-xl transition-all duration-200 border border-gray-700/30 hover:border-gray-600/50"
            >
              Skip for Now
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}