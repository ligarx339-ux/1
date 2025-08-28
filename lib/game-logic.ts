import type { User } from "@/types"

// Updated game config for DRX mining system
export const GAME_CONFIG = {
  REFERRAL_BONUS: 200, // DRX
  BASE_MINING_RATE: 0.001, // DRX per second
  WELCOME_BONUS: 100, // DRX
  JACKPOT_COOLDOWN: 3600000, // 1 hour
  MIN_CLAIM_TIME: 1800, // 30 minutes minimum mining time
  MIN_CLAIM_INTERVAL: 300, // 5 minutes minimum between claims
  MAX_MINING_TIME: 86400, // 24 hours maximum mining time
  DAILY_MINING_REWARD: 100, // DRX for 30min continuous mining
  CRITICAL_CHANCE: 0.02,
  JACKPOT_CHANCE: 0.0005,
  MAX_LEVEL: 50,
  XP_PER_LEVEL: 100,
  DRX_TO_UC_RATE: 1, // 1 DRX = 1 UC (can be changed)
  BASE_XP_REWARD: 50, // Base XP for 30 minutes of mining
  REFERRAL_XP_BONUS: 60, // XP bonus for referrals
  MINING_SPEED_MULTIPLIER: 1.2, // Added: Multiplier for mining speed boost
  CLAIM_TIME_REDUCTION: 60, // Added: Reduction in claim time per level (seconds)
  MINING_RATE_MULTIPLIER: 1.5, // Added: Multiplier for mining rate boost
}

export const gameLogic = {
  calculateMiningRewards(user: User, miningDuration: number): { earned: number; type: "normal" | "bonus"; xp: number } {
    const baseRate = user.miningRate || GAME_CONFIG.BASE_MINING_RATE
    let earned = baseRate * miningDuration // duration in seconds
    let type: "normal" | "bonus" = "normal"
    
    // Calculate XP based on mining duration (30 minutes = 30 XP base)
    let xp = Math.floor((miningDuration / 1800) * GAME_CONFIG.BASE_XP_REWARD) // 1800 seconds = 30 minutes
    
    // Apply boost multipliers to XP
    const miningSpeedMultiplier = user.boosts.miningSpeedLevel || 1
    const miningRateMultiplier = user.boosts.miningRateLevel || 1
    xp = Math.floor(xp * Math.max(miningSpeedMultiplier, miningRateMultiplier) * 0.5)

    // Bonus for 24h continuous mining
    if (miningDuration >= GAME_CONFIG.MAX_MINING_TIME) {
      earned += GAME_CONFIG.DAILY_MINING_REWARD
      xp += 100 // Bonus XP for 24h mining
      type = "bonus"
    }

    return { earned, type, xp }
  },

  calculateLevel(xp: number): { level: number; currentXP: number; xpForNext: number } {
    let level = 1
    let remainingXP = xp
    let totalXPNeeded = 0

    while (remainingXP >= this.getXpForLevel(level)) {
      const xpForThisLevel = this.getXpForLevel(level)
      remainingXP -= xpForThisLevel
      totalXPNeeded += xpForThisLevel
      level++
    }

    return {
      level,
      currentXP: remainingXP,
      xpForNext: this.getXpForLevel(level),
    }
  },

  getXpForLevel(level: number): number {
    if (level === 1) return 100
    // Each level requires more XP progressively
    return 100 + (level - 1) * 50
  },

  calculateRank(totalEarned: number): { rank: number; title: string; nextRankAt: number; icon: string } {
    const ranks = [
      { threshold: 0, title: "Rookie Miner", icon: "🥉" },
      { threshold: 1000, title: "Bronze Miner", icon: "🥉" },
      { threshold: 5000, title: "Silver Miner", icon: "🥈" },
      { threshold: 15000, title: "Gold Miner", icon: "🥇" },
      { threshold: 50000, title: "Platinum Miner", icon: "💎" },
      { threshold: 150000, title: "Diamond Miner", icon: "💎" },
      { threshold: 500000, title: "Master Miner", icon: "👑" },
      { threshold: 1500000, title: "Grandmaster Miner", icon: "👑" },
      { threshold: 5000000, title: "Legend Miner", icon: "🏆" },
      { threshold: 15000000, title: "Mythical Miner", icon: "⭐" },
      { threshold: 50000000, title: "Ultimate Miner", icon: "🌟" },
    ]

    let currentRank = 1
    let currentTitle = ranks[0].title
    let currentIcon = ranks[0].icon
    let nextRankAt = ranks[1]?.threshold || 0

    for (let i = 0; i < ranks.length; i++) {
      if (totalEarned >= ranks[i].threshold) {
        currentRank = i + 1
        currentTitle = ranks[i].title
        currentIcon = ranks[i].icon
        nextRankAt = ranks[i + 1]?.threshold || ranks[i].threshold
      } else {
        break
      }
    }

    return {
      rank: currentRank,
      title: currentTitle,
      nextRankAt,
      icon: currentIcon,
    }
  },

  getBoostCost(boostType: "miningSpeed" | "claimTime" | "miningRate", currentLevel: number): number {
    const baseCosts = {
      miningSpeed: 100,
      claimTime: 150,
      miningRate: 200,
    }

    const baseCost = baseCosts[boostType]
    // Each level costs more than the previous, starting from level 1
    return Math.floor(baseCost * Math.pow(1.5, Math.max(0, currentLevel - 1)))
  },

  getNextBoostValue(boostType: "miningSpeed" | "claimTime" | "miningRate", currentLevel: number, user: User): string {
    switch (boostType) {
      case "miningSpeed":
        const nextSpeedMultiplier = Math.pow(GAME_CONFIG.MINING_SPEED_MULTIPLIER, currentLevel)
        return `${nextSpeedMultiplier.toFixed(1)}x`
      case "claimTime":
        const currentTime = user.minClaimTime || GAME_CONFIG.MIN_CLAIM_TIME
        const nextTime = Math.max(300, GAME_CONFIG.MIN_CLAIM_TIME - (GAME_CONFIG.CLAIM_TIME_REDUCTION * currentLevel))
        return this.formatTime(nextTime)
      case "miningRate":
        const nextRateMultiplier = Math.pow(GAME_CONFIG.MINING_RATE_MULTIPLIER, currentLevel)
        const nextRate = GAME_CONFIG.BASE_MINING_RATE * nextRateMultiplier
        return `${this.formatNumberPrecise(nextRate)}/s`
      default:
        return "Unknown"
    }
  },

  formatNumber(num: number | undefined | null): string {
    const safeNum = typeof num === "number" && !isNaN(num) ? num : 0

    if (safeNum >= 1000000000) {
      return Math.floor(safeNum / 1000000000) + "B"
    } else if (safeNum >= 1000000) {
      return Math.floor(safeNum / 1000000) + "M"
    } else if (safeNum >= 1000) {
      return Math.floor(safeNum / 1000) + "K"
    }
    return Math.floor(safeNum).toString()
  },

  formatNumberPrecise(num: number | undefined | null): string {
    const safeNum = typeof num === "number" && !isNaN(num) ? num : 0
    // Remove trailing zeros and unnecessary decimal places
    if (safeNum === 0) return "0"
    if (safeNum >= 1) return safeNum.toFixed(3).replace(/\.?0+$/, "")
    return safeNum.toFixed(6).replace(/\.?0+$/, "")
  },

  formatTime(seconds: number): string {
    const safeSeconds = typeof seconds === "number" && !isNaN(seconds) ? Math.floor(seconds) : 0
    const hours = Math.floor(safeSeconds / 3600)
    const minutes = Math.floor((safeSeconds % 3600) / 60)
    const secs = safeSeconds % 60

    if (hours > 0) {
      return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
    } else {
      return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
    }
  },

  canClaimMining(user: User): boolean {
    if (!user.isMining || !user.miningStartTime) return false
    
    const now = Date.now()
    const miningDuration = Math.floor((now - user.miningStartTime) / 1000)
    const timeSinceLastClaim = Math.floor((now - (user.lastClaimTime || 0)) / 1000)
    
    return miningDuration >= (user.minClaimTime || GAME_CONFIG.MIN_CLAIM_TIME) && 
           timeSinceLastClaim >= GAME_CONFIG.MIN_CLAIM_INTERVAL
  },

  getMiningDuration(user: User): number {
    if (!user.isMining || !user.miningStartTime) return 0
    
    const now = Date.now()
    return Math.floor((now - user.miningStartTime) / 1000)
  },

  calculatePendingRewards(user: User): number {
    const duration = this.getMiningDuration(user)
    if (duration === 0) return 0
    
    // Apply mining time limits
    const limitedDuration = Math.min(duration, GAME_CONFIG.MAX_MINING_TIME)
    
    const { earned } = this.calculateMiningRewards(user, limitedDuration)
    return earned
  },

  getRemainingClaimTime(user: User): number {
    if (!user.isMining || !user.miningStartTime) return 0
    
    const now = Date.now()
    const miningDuration = Math.floor((now - user.miningStartTime) / 1000)
    const minTime = user.minClaimTime || GAME_CONFIG.MIN_CLAIM_TIME
    
    return Math.max(0, minTime - miningDuration)
  },
}