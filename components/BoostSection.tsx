"use client"

import type { User } from "@/types"
import { gameLogic, GAME_CONFIG } from "@/lib/game-logic"
import { ArrowUp, Zap, Clock, TrendingUp } from "lucide-react"

interface BoostSectionProps {
  user: User
  onUpgrade: (boostType: "miningSpeed" | "claimTime" | "miningRate") => Promise<any>
  onOpenRank: () => void
}

export const BoostSection = ({ user, onUpgrade, onOpenRank }: BoostSectionProps) => {
  // Prevent undefined errors with defaults
  const boostsData = user.boosts || { miningSpeedLevel: 1, claimTimeLevel: 1, miningRateLevel: 1 }

  const miningSpeedCost = gameLogic.getBoostCost("miningSpeed", boostsData.miningSpeedLevel)
  const claimTimeCost = gameLogic.getBoostCost("claimTime", boostsData.claimTimeLevel)
  const miningRateCost = gameLogic.getBoostCost("miningRate", boostsData.miningRateLevel)
  const { rank, icon } = gameLogic.calculateRank(user.totalEarned || 0)

  // Calculate current values
  const currentMiningRate = user.miningRate || GAME_CONFIG.BASE_MINING_RATE
  const currentClaimTime = user.minClaimTime || GAME_CONFIG.MIN_CLAIM_TIME
  const currentMiningSpeed = Math.pow(
    GAME_CONFIG.MINING_SPEED_MULTIPLIER,
    (boostsData.miningSpeedLevel || 1) - 1
  )

  const boosts = [
    {
      id: "miningSpeed",
      title: "Mining Speed",
      description: "Increase mining efficiency",
      icon: <Zap className="w-5 h-5" />,
      level: boostsData.miningSpeedLevel,
      current: `${currentMiningSpeed.toFixed(1)}x`,
      next: gameLogic.getNextBoostValue("miningSpeed", boostsData.miningSpeedLevel, user),
      cost: miningSpeedCost,
      onUpgrade: () => onUpgrade("miningSpeed"),
    },
    {
      id: "claimTime",
      title: "Claim Time",
      description: "Reduce minimum claim time",
      icon: <Clock className="w-5 h-5" />,
      level: boostsData.claimTimeLevel,
      current: gameLogic.formatTime(currentClaimTime),
      next: gameLogic.getNextBoostValue("claimTime", boostsData.claimTimeLevel, user),
      cost: claimTimeCost,
      onUpgrade: () => onUpgrade("claimTime"),
    },
    {
      id: "miningRate",
      title: "Mining Rate",
      description: "Increase DRX per second",
      icon: <TrendingUp className="w-5 h-5" />,
      level: boostsData.miningRateLevel,
      current: `${gameLogic.formatNumberPrecise(currentMiningRate)}/s`,
      next: gameLogic.getNextBoostValue("miningRate", boostsData.miningRateLevel, user),
      cost: miningRateCost,
      onUpgrade: () => onUpgrade("miningRate"),
    },
  ]

  return (
    <div className="px-4 pb-32">
      {/* Section Header with Rank */}
      <div className="flex items-center gap-3 mb-6">
        <div className="bg-black/30 backdrop-blur-md border border-green-500/30 rounded-2xl p-4 flex-1 shadow-lg">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 bg-gradient-to-br from-green-400 to-blue-500 rounded-xl flex items-center justify-center text-2xl shadow-lg">
              🚀
            </div>
            <div>
              <h2 className="text-lg font-bold text-white font-display">Mining Boosts</h2>
              <p className="text-gray-400 text-sm">Upgrade mining power</p>
            </div>
          </div>
        </div>

        {/* Rank Icon */}
        <button
          onClick={onOpenRank}
          className="bg-gradient-to-br from-yellow-500/20 to-orange-500/20 border border-yellow-500/40 rounded-xl p-3 hover:border-yellow-500/60 transition-all duration-300 hover:scale-105 shadow-lg hover:shadow-yellow-500/30"
        >
          <div className="text-center">
            <div className="text-xl mb-1 animate-bounce">{icon}</div>
            <div className="text-xs text-yellow-400 font-bold">#{rank}</div>
          </div>
        </button>
      </div>

      {/* Boost Cards */}
      <div className="space-y-4">
        {boosts.map((boost) => (
          <div
            key={boost.id}
            className="bg-black/30 backdrop-blur-md border border-gray-700/30 rounded-2xl p-5 hover:border-green-500/40 transition-all duration-300 hover:shadow-lg hover:shadow-green-500/10"
          >
            {/* Header */}
            <div className="flex items-center gap-4 mb-4">
              <div className="w-14 h-14 bg-gradient-to-br from-green-400 to-blue-500 rounded-xl flex items-center justify-center text-white shadow-lg">
                {boost.icon}
              </div>
              <div className="flex-1">
                <h3 className="text-base font-bold text-white font-display">{boost.title}</h3>
                <p className="text-sm text-gray-400">{boost.description}</p>
              </div>
              <div className="bg-green-500/20 border border-green-500/30 rounded-xl px-3 py-2">
                <span className="text-sm font-bold text-green-400 uppercase tracking-wide">Lv.{boost.level}</span>
              </div>
            </div>

            {/* Stats Grid */}
            <div className="grid grid-cols-3 gap-4 mb-4 p-4 bg-gradient-to-r from-black/30 to-gray-900/30 rounded-xl border border-gray-700/20 backdrop-blur-sm">
              <div className="text-center">
                <p className="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-2">⚡ Current</p>
                <p className="text-base font-bold text-green-400">{boost.current}</p>
              </div>
              <div className="text-center">
                <p className="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-2">🚀 Next Level</p>
                <p className="text-base font-bold text-blue-400">{boost.next}</p>
              </div>
              <div className="text-center">
                <p className="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-2">💰 Cost</p>
                <p className="text-base font-bold text-orange-400">{gameLogic.formatNumber(boost.cost)} DRX</p>
              </div>
            </div>

            {/* Upgrade Button */}
            <button
              onClick={boost.onUpgrade}
              disabled={user.balance < boost.cost}
              className="w-full bg-gradient-to-r from-green-400 to-blue-500 hover:from-green-500 hover:to-blue-600 disabled:from-gray-700 disabled:to-gray-800 disabled:cursor-not-allowed text-white font-bold py-3 px-4 rounded-xl transition-all duration-200 hover:scale-105 disabled:hover:scale-100 shadow-lg hover:shadow-xl flex items-center justify-center gap-2"
            >
              <ArrowUp className="w-5 h-5" />
              Upgrade {boost.title}
            </button>
          </div>
        ))}
      </div>
    </div>
  )
}
