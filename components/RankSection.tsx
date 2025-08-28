"use client"

import type { User } from "@/types"
import { gameLogic } from "@/lib/game-logic"
import { Trophy, Star, TrendingUp, Target } from "lucide-react"

interface RankSectionProps {
  user: User
  onNavigateToTap: () => void
}

export const RankSection = ({ user, onNavigateToTap }: RankSectionProps) => {
  const { rank, title, nextRankAt, icon } = gameLogic.calculateRank(user.totalEarned)
  const progress = nextRankAt > 0 ? ((user.totalEarned % nextRankAt) / nextRankAt) * 100 : 100
  const remaining = Math.max(0, nextRankAt - user.totalEarned)

  const rankStats = [
    {
      icon: <Trophy className="w-6 h-6" />,
      label: "Current Rank",
      value: `#${rank}`,
      color: "text-yellow-400",
    },
    {
      icon: <Star className="w-6 h-6" />,
      label: "Total Earned",
      value: gameLogic.formatNumber(user.totalEarned),
      color: "text-green-400",
    },
    {
      icon: <TrendingUp className="w-6 h-6" />,
      label: "Next Rank",
      value: remaining > 0 ? gameLogic.formatNumber(remaining) : "MAX",
      color: "text-blue-400",
    },
    {
      icon: <Target className="w-6 h-6" />,
      label: "Progress",
      value: `${Math.round(progress)}%`,
      color: "text-purple-400",
    },
  ]

  return (
    <div className="px-4 pb-32">
      {/* Section Header */}
      <div className="bg-black/30 backdrop-blur-md border border-green-500/30 rounded-2xl p-4 mb-6 shadow-lg">
        <div className="flex items-center gap-4">
          <div className="w-14 h-14 bg-gradient-to-br from-green-400 to-blue-500 rounded-xl flex items-center justify-center text-3xl shadow-lg">
            🏆
          </div>
          <div>
            <h2 className="text-xl font-bold text-white font-display">Rank & Progress</h2>
            <p className="text-gray-400 text-sm">Your mining achievements</p>
          </div>
        </div>
      </div>

      {/* Current Rank Display */}
      <div className="bg-gradient-to-br from-yellow-500/10 to-orange-500/10 border-2 border-yellow-500/40 rounded-2xl p-6 mb-6 text-center shadow-xl shadow-yellow-500/20">
        <div className="text-6xl mb-4 animate-bounce">{icon}</div>
        <h3 className="text-2xl font-bold text-white font-display mb-2">{title}</h3>
        <div className="flex items-center justify-center gap-2 mb-4">
          <span className="text-yellow-400 font-bold text-lg">Rank #{rank}</span>
          <div className="w-2.5 h-2.5 bg-yellow-400 rounded-full animate-pulse" />
        </div>

        {/* Progress to Next Rank */}
        {remaining > 0 && (
          <div className="mt-4">
            <div className="flex justify-between text-sm text-gray-300 mb-2">
              <span className="font-semibold">Progress to next rank</span>
              <span className="font-semibold">{gameLogic.formatNumber(remaining)} UC needed</span>
            </div>
            <div className="w-full h-3 bg-gray-700 rounded-full overflow-hidden border border-gray-600/50 shadow-inner">
              <div
                className="h-full bg-gradient-to-r from-yellow-400 to-orange-500 rounded-full transition-all duration-1000 relative overflow-hidden"
                style={{ width: `${progress}%` }}
              >
                <div className="absolute inset-0 bg-white/30 animate-pulse" />
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Rank Stats */}
      <div className="grid grid-cols-2 gap-4 mb-6">
        {rankStats.map((stat, index) => (
          <div
            key={index}
            className="bg-black/30 backdrop-blur-md border border-gray-700/30 rounded-2xl p-4 text-center hover:border-yellow-500/40 transition-all duration-300 hover:shadow-lg hover:shadow-yellow-500/10"
          >
            <div
              className={`w-14 h-14 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl flex items-center justify-center text-white text-2xl mx-auto mb-3 shadow-lg`}
            >
              {stat.icon}
            </div>
            <div className={`text-xl font-bold mb-1 ${stat.color}`}>{stat.value}</div>
            <div className="text-xs text-gray-400 font-semibold uppercase tracking-wide">{stat.label}</div>
          </div>
        ))}
      </div>

      {/* Rank Benefits Info */}
      <div className="bg-black/30 backdrop-blur-md border border-gray-700/30 rounded-2xl p-5 mb-6">
        <div className="flex items-center gap-4 mb-4">
          <div className="w-14 h-14 bg-gradient-to-br from-purple-400 to-pink-500 rounded-xl flex items-center justify-center text-white text-2xl shadow-lg">
            🎁
          </div>
          <div>
            <h3 className="text-lg font-bold text-white font-display">Rank Benefits</h3>
            <p className="text-sm text-gray-400">Higher ranks unlock special features</p>
          </div>
        </div>

        <div className="space-y-3">
          <div className="flex items-center gap-3 p-3 bg-black/20 rounded-xl border border-gray-700/20">
            <div className="text-2xl">💎</div>
            <div>
              <div className="text-white font-semibold">Exclusive Titles</div>
              <div className="text-sm text-gray-400">Show off your mining prowess</div>
            </div>
          </div>

          <div className="flex items-center gap-3 p-3 bg-black/20 rounded-xl border border-gray-700/20">
            <div className="text-2xl">⚡</div>
            <div>
              <div className="text-white font-semibold">Bonus Multipliers</div>
              <div className="text-sm text-gray-400">Higher ranks get better rewards</div>
            </div>
          </div>

          <div className="flex items-center gap-3 p-3 bg-black/20 rounded-xl border border-gray-700/20">
            <div className="text-2xl">🏆</div>
            <div>
              <div className="text-white font-semibold">Leaderboard Priority</div>
              <div className="text-sm text-gray-400">Stand out in rankings</div>
            </div>
          </div>
        </div>
      </div>

      {/* Action Button */}
      <button
        onClick={onNavigateToTap}
        className="w-full bg-gradient-to-r from-yellow-400 to-orange-500 hover:from-yellow-500 hover:to-orange-600 text-white font-bold py-4 px-6 rounded-2xl transition-all duration-200 hover:scale-105 shadow-lg hover:shadow-xl flex items-center justify-center gap-3"
      >
        <span className="text-2xl">⚡</span>
        <span>Start Mining to Rank Up!</span>
      </button>
    </div>
  )
}
