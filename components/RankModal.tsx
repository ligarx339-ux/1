"use client"

import { useState, useEffect } from "react"
import type { User } from "@/types"
import { gameLogic } from "@/lib/game-logic"
import { X } from "lucide-react"
import Image from "next/image"
import { apiService } from "@/lib/api"

interface RankModalProps {
  isOpen: boolean
  onClose: () => void
  user: User
}

interface LeaderboardUser {
  id: string
  firstName: string
  lastName?: string
  avatarUrl?: string
  totalEarned: number
  level: number
  xp: number
  rank: number
}

export const RankModal = ({ isOpen, onClose, user }: RankModalProps) => {
  const [leaderboard, setLeaderboard] = useState<LeaderboardUser[]>([])
  const [activeTab, setActiveTab] = useState<"balance" | "level">("balance")
  const [loading, setLoading] = useState(true)
  const [userRank, setUserRank] = useState(0)

  useEffect(() => {
    if (isOpen || activeTab) {
      loadLeaderboard()
    }
  }, [isOpen, activeTab])

  const loadLeaderboard = async () => {
    try {
      setLoading(true)

      // Get all users from PHP backend
      const usersData = await apiService.getGlobalLeaderboard(activeTab === 'balance' ? 'balance' : 'level')

      // Sort by active tab criteria
      const sortedUsers = usersData
        .sort((a, b) => {
          if (activeTab === "balance") {
            return b.totalEarned - a.totalEarned
          } else {
            const aLevel = gameLogic.calculateLevel(a.xp || 0).level
            const bLevel = gameLogic.calculateLevel(b.xp || 0).level
            // Sort by XP first (higher XP = higher rank)
            return (b.xp || 0) - (a.xp || 0)
          }
        })
        .map((userData, index) => ({
          ...userData,
          level: gameLogic.calculateLevel(userData.xp || 0).level,
          rank: index + 1,
        }))

      setLeaderboard(sortedUsers)

      // Find current user's rank
      const currentUserRank = sortedUsers.findIndex((u) => u.id === user.id) + 1
      setUserRank(currentUserRank || 0)
    } catch (error) {
      console.error("Failed to load leaderboard:", error)
    } finally {
      setLoading(false)
    }
  }

  const getRankIcon = (rank: number) => {
    if (rank === 1) return { icon: "👑", color: "text-yellow-400" }
    if (rank === 2) return { icon: "🥈", color: "text-gray-300" }
    if (rank === 3) return { icon: "🥉", color: "text-orange-400" }
    if (rank <= 10) return { icon: "🏆", color: "text-purple-400" }
    if (rank <= 50) return { icon: "🎖️", color: "text-blue-400" }
    return { icon: "⭐", color: "text-green-400" }
  }

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
      <div className="w-full max-w-sm bg-gradient-to-br from-gray-900/95 to-black/95 backdrop-blur-md border-2 border-yellow-500/30 rounded-2xl shadow-xl shadow-yellow-500/20 max-h-[85vh] overflow-y-auto">
        {/* Header */}
        <div className="flex items-center justify-between p-4 border-b border-gray-700/30 bg-gradient-to-r from-yellow-500/10 to-orange-500/10">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-xl flex items-center justify-center text-lg shadow-lg">
              🏆
            </div>
            <div>
              <h2 className="text-lg font-bold text-white font-display">Leaderboard</h2>
              <p className="text-xs text-gray-300">Top Players</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-8 h-8 bg-gray-700/20 hover:bg-red-500/20 border border-gray-700/30 hover:border-red-500/50 rounded-xl flex items-center justify-center text-gray-400 hover:text-red-400 transition-all duration-200"
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Tabs */}
        <div className="flex gap-1 p-3 bg-black/20">
          <button
            onClick={() => setActiveTab("balance")}
            className={`flex-1 py-2 px-3 rounded-lg font-semibold text-sm transition-all duration-200 ${
              activeTab === "balance"
                ? "bg-gradient-to-r from-green-400 to-blue-500 text-white shadow-lg"
                : "text-gray-400 hover:text-white hover:bg-gray-800/30"
            }`}
          >
            💰 Top Balance
          </button>
          <button
            onClick={() => setActiveTab("level")}
            className={`flex-1 py-2 px-3 rounded-lg font-semibold text-sm transition-all duration-200 ${
              activeTab === "level"
                ? "bg-gradient-to-r from-green-400 to-blue-500 text-white shadow-lg"
                : "text-gray-400 hover:text-white hover:bg-gray-800/30"
            }`}
          >
            ⭐ Top Level
          </button>
        </div>

        {/* Your Rank */}
        {userRank > 0 && (
          <div className="p-3 bg-gradient-to-r from-green-500/10 to-blue-500/10 border-b border-gray-700/30">
            <div className="flex items-center gap-3 p-2 bg-black/30 rounded-xl border border-green-500/30">
              <div className="text-lg">{getRankIcon(userRank).icon}</div>
              <div className="flex-1">
                <div className="text-sm text-white font-bold">Your Position</div>
                <div className="text-xs text-gray-300">
                  #{userRank} of {leaderboard.length}
                </div>
              </div>
              <div className="text-right">
                <div className="text-sm text-green-400 font-bold">
                  {activeTab === "balance" 
                    ? gameLogic.formatNumber(user.totalEarned) 
                    : `Level ${gameLogic.calculateLevel(user.xp).level}`
                  }
                </div>
                <div className="text-xs text-gray-400">
                  {activeTab === "balance" ? "DRX Earned" : "Current Level"}
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Leaderboard */}
        <div className="flex-1 overflow-y-auto">
          {loading ? (
            <div className="text-center py-8">
              <div className="text-3xl mb-3 animate-spin text-green-400">⏳</div>
              <p className="text-gray-400">Loading leaderboard...</p>
            </div>
          ) : leaderboard.length === 0 ? (
            <div className="text-center py-8">
              <div className="text-4xl mb-3">🏆</div>
              <h3 className="text-xl font-bold text-white font-display mb-2">No Data Available</h3>
              <p className="text-gray-400">Leaderboard will update soon!</p>
            </div>
          ) : (
            <div className="p-4 space-y-2">
              {leaderboard.map((player, index) => {
                const rankInfo = getRankIcon(player.rank)
                const isCurrentUser = player.id === user.id
                const displayName = `${player.firstName} ${player.lastName || ""}`.trim()
                const avatarUrl =
                  player.avatarUrl ||
                  `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName.replace(/\s+/g, "+"))}&background=4CAF50&color=fff&size=40`

                return (
                  <div
                    key={player.id}
                    className={`flex items-center gap-2 p-2 rounded-lg transition-all duration-300 ${
                      isCurrentUser
                        ? "bg-gradient-to-r from-green-500/20 to-blue-500/20 border border-green-500/40"
                        : "bg-black/20 border border-gray-700/20"
                    }`}
                  >
                    {/* Rank */}
                    <div className="flex items-center gap-1 min-w-[50px]">
                      <span className={`text-sm ${rankInfo.color}`}>{rankInfo.icon}</span>
                      <span className={`text-sm font-bold ${isCurrentUser ? "text-green-400" : "text-white"}`}>
                        #{player.rank}
                      </span>
                    </div>

                    {/* Avatar */}
                    <Image
                      src={avatarUrl || "/placeholder.svg"}
                      alt="Player Avatar"
                      width={32}
                      height={32}
                      className={`w-8 h-8 rounded-full border ${isCurrentUser ? "border-green-500" : "border-gray-500"}`}
                    />

                    {/* Info */}
                    <div className="flex-1 min-w-0">
                      <div className={`text-sm font-semibold truncate ${isCurrentUser ? "text-green-400" : "text-white"}`}>
                        {displayName}
                        {isCurrentUser && " (You)"}
                      </div>
                      <div className="text-xs text-gray-400">
                        {activeTab === "balance" 
                          ? `${gameLogic.formatNumber(player.totalEarned)} DRX`
                          : `Level ${player.level} (${gameLogic.formatNumber(player.xp || 0)} XP)`
                        }
                      </div>
                    </div>

                    {/* Badge for top ranks */}
                    {player.rank <= 3 && (
                      <div
                        className={`px-2 py-1 rounded-lg text-xs font-bold shadow-sm ${
                          player.rank === 1
                            ? "bg-yellow-500/20 text-yellow-400"
                            : player.rank === 2
                              ? "bg-gray-500/20 text-gray-300"
                              : "bg-orange-500/20 text-orange-400"
                        }`}
                      >
                        {player.rank === 1 ? "1ST" : player.rank === 2 ? "2ND" : "3RD"}
                      </div>
                    )}
                  </div>
                )
              })}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}