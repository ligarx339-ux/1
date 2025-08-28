"use client"

import { useState, useEffect } from "react"
import type { User } from "@/types"
import { gameLogic } from "@/lib/game-logic"
import { apiService } from "@/lib/api"
import { Gift, Copy, Trophy } from "lucide-react"
import Image from "next/image"
import { useToast } from "@/hooks/use-toast"

interface FriendsData {
  count: number
  totalUC: number
  referrals: Record<
    string,
    {
      date: string
      earned: number
      firstName: string
      lastName: string
      avatarUrl: string
    }
  >
}

interface FriendsSectionProps {
  user: User
  onCopyReferralLink: () => Promise<any>
}

export const FriendsSection = ({ user, onCopyReferralLink }: FriendsSectionProps) => {
  const [activeTab, setActiveTab] = useState<"friends" | "leaderboard">("friends")
  const [friendsData, setFriendsData] = useState<FriendsData>({ count: 0, totalUC: 0, referrals: {} })
  const { toast } = useToast()
  const [leaderboard, setLeaderboard] = useState<
    Array<{
      id: string
      count: number
      earned: number
      user: any
    }>
  >([])
  const [loading, setLoading] = useState(true)

  const handleCopyReferralLink = async () => {
    const result = await onCopyReferralLink()
    if (result.success) {
      toast({
        title: "🔗 Link Copied!",
        description: "Referral link copied and shared successfully!",
        duration: 3000,
      })
    }
  }

  const referralLink = `https://t.me/UCCoinUltraBot?start=ref_${user.id}`

  useEffect(() => {
    loadFriendsData()
  }, [user.id])

  const loadFriendsData = async () => {
    try {
      const data = await apiService.getReferralData(user.id)
      setFriendsData(data)
    } catch (error) {
      console.error("Failed to load friends data:", error)
    } finally {
      setLoading(false)
    }
  }

  const loadLeaderboard = async () => {
    if (activeTab !== "leaderboard") return

    try {
      setLoading(true)
      const leaderboardData = await apiService.getLeaderboard()
      setLeaderboard(leaderboardData)
    } catch (error) {
      console.error("Failed to load leaderboard:", error)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    if (activeTab === "leaderboard") {
      loadLeaderboard()
    }
  }, [activeTab])

  const referredUsers = Object.entries(friendsData.referrals || {})
    .map(([id, refData]) => ({
      id,
      date: refData.date,
      earned: refData.earned || 0,
      firstName: refData.firstName,
      lastName: refData.lastName || "",
      avatarUrl: refData.avatarUrl || "",
    }))
    .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())

  return (
    <div className="pb-4">
      {/* Section Header */}
      <div className="bg-black/30 backdrop-blur-md border border-green-500/30 rounded-xl p-3 mb-4 shadow-lg">
        <div className="flex items-center gap-4">
          <div className="w-10 h-10 bg-gradient-to-br from-green-400 to-blue-500 rounded-lg flex items-center justify-center text-xl shadow-lg">
            👥
          </div>
          <div>
            <h2 className="text-base font-bold text-white font-display">Friends & Referrals</h2>
            <p className="text-gray-400 text-xs">Invite friends and earn</p>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-1 mb-4 p-1 bg-black/30 backdrop-blur-md border border-gray-700/30 rounded-lg">
        <button
          onClick={() => setActiveTab("friends")}
          className={`flex-1 py-2 px-3 rounded-lg font-semibold text-sm transition-all duration-200 ${
            activeTab === "friends"
              ? "bg-gradient-to-r from-green-400 to-blue-500 text-white shadow-lg"
              : "text-gray-400 hover:text-white hover:bg-gray-800/30"
          }`}
        >
          My Friends
        </button>
        <button
          onClick={() => setActiveTab("leaderboard")}
          className={`flex-1 py-2 px-3 rounded-lg font-semibold text-sm transition-all duration-200 ${
            activeTab === "leaderboard"
              ? "bg-gradient-to-r from-green-400 to-blue-500 text-white shadow-lg"
              : "text-gray-400 hover:text-white hover:bg-gray-800/30"
          }`}
        >
          Leaderboard
        </button>
      </div>

      {/* Referral Section */}
      <div className="bg-black/30 backdrop-blur-md border border-gray-700/30 rounded-xl p-3 mb-4">
        <div className="flex items-center gap-3 mb-3">
          <div className="w-10 h-10 bg-gradient-to-br from-green-400 to-blue-500 rounded-lg flex items-center justify-center text-white text-lg shadow-lg">
            <Gift className="w-7 h-7" />
          </div>
          <div>
            <h3 className="text-sm font-bold text-white font-display">Referral Program</h3>
            <p className="text-xs text-gray-400">Earn 200 DRX per friend</p>
          </div>
        </div>

        {/* Referral Stats */}
        <div className="grid grid-cols-2 gap-2 mb-3">
          <div className="text-center p-3 bg-black/20 rounded-xl border border-gray-700/20">
            <div className="text-lg font-bold text-white mb-1">{friendsData.count}</div>
            <div className="text-sm text-gray-400 font-semibold uppercase tracking-wide">Friends Invited</div>
          </div>
          <div className="text-center p-3 bg-black/20 rounded-xl border border-gray-700/20">
            <div className="text-lg font-bold text-white mb-1">{gameLogic.formatNumber(friendsData.totalUC)}</div>
            <div className="text-sm text-gray-400 font-semibold uppercase tracking-wide">DRX Earned</div>
          </div>
        </div>

        {/* Referral Link */}
        <div>
          <div className="text-sm text-gray-400 font-semibold uppercase tracking-wide mb-2">Your Referral Link</div>
          <div className="flex gap-2">
            <input
              type="text"
              value={referralLink}
              readOnly
              className="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono focus:outline-none focus:border-green-500"
            />
            <button
              onClick={handleCopyReferralLink}
              className="bg-gradient-to-r from-green-400 to-blue-500 hover:from-green-500 hover:to-blue-600 text-white font-bold px-4 py-2 rounded-lg transition-all duration-200 hover:scale-105 flex items-center gap-1 shadow-lg"
            >
              <Copy className="w-4 h-4" />
              <span className="hidden sm:inline">Copy</span>
            </button>
          </div>
        </div>
      </div>

      {/* Tab Content */}
      {activeTab === "friends" ? (
        <div>
          <h3 className="text-lg font-bold text-white font-display mb-4">Friends List</h3>
          {loading ? (
            <div className="text-center py-12">
              <div className="text-4xl mb-4 animate-spin text-green-400">⏳</div>
              <p className="text-gray-400">Loading friends...</p>
            </div>
          ) : referredUsers.length === 0 ? (
            <div className="text-center py-12">
              <div className="text-6xl mb-4">👥</div>
              <h3 className="text-xl font-bold text-white font-display mb-2">No friends invited yet</h3>
              <p className="text-gray-400">Start sharing your referral link!</p>
            </div>
          ) : (
            <div className="space-y-3">
              {referredUsers.map((friend) => {
                const displayName = friend.firstName + (friend.lastName ? ` ${friend.lastName}` : "")
                const avatarUrl =
                  friend.avatarUrl ||
                  `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName.replace(/\s+/g, "+"))}&background=4CAF50&color=fff&size=40`

                return (
                  <div
                    key={friend.id}
                    className="flex items-center gap-4 p-4 bg-black/20 backdrop-blur-md border border-gray-700/20 rounded-xl hover:border-green-500/30 transition-all duration-300"
                  >
                    <Image
                      src={avatarUrl || "/placeholder.svg"}
                      alt="Friend Avatar"
                      width={40}
                      height={40}
                      className="rounded-full border-2 border-green-500"
                    />
                    <div className="flex-1">
                      <div className="font-semibold text-white">{displayName}</div>
                      <div className="text-sm text-gray-400">{new Date(friend.date).toLocaleDateString()}</div>
                    </div>
                    <div className="text-right font-bold text-green-400">+{friend.earned} DRX</div>
                  </div>
                )
              })}
            </div>
          )}
        </div>
      ) : (
        <div>
          <h3 className="text-lg font-bold text-white font-display mb-4 flex items-center gap-2">
            <Trophy className="w-5 h-5" />
            Top 100 Referrers
          </h3>
          {loading ? (
            <div className="text-center py-12">
              <div className="text-4xl mb-4 animate-spin text-green-400">⏳</div>
              <p className="text-gray-400">Loading leaderboard...</p>
            </div>
          ) : leaderboard.length === 0 ? (
            <div className="text-center py-12">
              <div className="text-6xl mb-4">🏆</div>
              <h3 className="text-xl font-bold text-white font-display mb-2">No Leaderboard Data</h3>
              <p className="text-gray-400">Be the first to invite friends!</p>
            </div>
          ) : (
            <div className="space-y-2 max-h-96 overflow-y-auto">
              {leaderboard.slice(0, 100).map((item, index) => {
                const displayName = item.user.firstName
                  ? `${item.user.firstName} ${item.user.lastName || ""}`
                  : item.id.substring(0, 8) + "..."

                const getRankIcon = (rank: number) => {
                  if (rank === 1) return "👑"
                  if (rank === 2) return "🥈"
                  if (rank === 3) return "🥉"
                  if (rank <= 10) return "🏆"
                  return "⭐"
                }

                const avatarUrl =
                  item.user.avatarUrl ||
                  `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName.replace(/\s+/g, "+"))}&background=4CAF50&color=fff&size=40`

                return (
                  <div
                    key={item.id}
                    className="flex items-center gap-3 p-3 bg-black/20 backdrop-blur-md border border-gray-700/20 rounded-xl hover:border-green-500/30 transition-all duration-300"
                  >
                    <div className="text-xl min-w-[30px] text-center">{getRankIcon(index + 1)}</div>
                    <div className="font-bold text-white min-w-[30px]">#{index + 1}</div>
                    <Image
                      src={avatarUrl || "/placeholder.svg"}
                      alt="User Avatar"
                      width={40}
                      height={40}
                      className="rounded-full border-2 border-green-500"
                    />
                    <div className="flex-1 min-w-0">
                      <div className="font-semibold text-white truncate">{displayName}</div>
                      <div className="text-sm text-gray-400">{item.count} referrals</div>
                    </div>
                    <div className="text-right font-bold text-green-400">{gameLogic.formatNumber(item.earned)} DRX</div>
                  </div>
                )
              })}
            </div>
          )}
        </div>
      )}
    </div>
  )
}
