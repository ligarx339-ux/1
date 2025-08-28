"use client"

import type React from "react"
import { useState, useEffect, useCallback } from "react"
import type { User, GameState } from "@/types"
import { apiService } from "@/lib/api"
import { gameLogic, GAME_CONFIG } from "@/lib/game-logic"
import { telegram } from "@/lib/telegram"
import { getUrlParameter, parseReferralFromUrl, parseRefAuthFromUrl, debounce } from "@/lib/utils"

const defaultUserData: User = {
  id: "",
  firstName: "User",
  lastName: "",
  avatarUrl: "",
  balance: 0, // DRX balance
  ucBalance: 0, // UC balance
  energyLimit: 500,
  multiTapValue: 1,
  rechargingSpeed: 1,
  tapBotPurchased: false,
  tapBotActive: false,
  bonusClaimed: false,
  pubgId: "",
  totalTaps: 0,
  totalEarned: 0,
  lastJackpotTime: 0,
  referredBy: "",
  referralCount: 0,
  level: 1,
  xp: 0,
  streak: 0,
  combo: 0,
  lastTapTime: 0,
  // Mining specific
  isMining: false,
  miningStartTime: 0,
  lastClaimTime: 0,
  pendingRewards: 0,
  miningRate: GAME_CONFIG.BASE_MINING_RATE,
  minClaimTime: GAME_CONFIG.MIN_CLAIM_TIME, // 30 minutes
  settings: {
    sound: true,
    vibration: true,
    notifications: true,
  },
  boosts: {
    miningSpeedLevel: 1,
    claimTimeLevel: 1,
    miningRateLevel: 1,
  },
  missions: {},
  withdrawals: [],
  conversions: [],
  joinedAt: Date.now(),
  lastActive: Date.now(),
  isReturningUser: false,
  dataInitialized: false,
  authKey: "",
}

export const useGameState = () => {
  const [user, setUser] = useState<User>(defaultUserData)
  const [gameState, setGameState] = useState<GameState>({
    isPlaying: false,
    soundEnabled: true,
    vibrationEnabled: true,
    dataLoaded: false,
    saveInProgress: false,
    lastSaveTime: 0,
  })
  const [loading, setLoading] = useState(true)

  // Define the actual save function
  const performSave = useCallback(async () => {
    if (!user.id || !user.authKey) return

    setGameState((prev) => ({ ...prev, saveInProgress: true }))
    try {
      await apiService.updateUser(user.id, user)
      setGameState((prev) => ({ ...prev, lastSaveTime: Date.now() }))
    } catch (error) {
      console.error("Failed to save user data:", error)
    } finally {
      setGameState((prev) => ({ ...prev, saveInProgress: false }))
    }
  }, [user])

  // Create a debounced version of the save function
  const debouncedSaveUserData = useCallback(debounce(performSave, 500), [performSave])

  // Initialize user and game
  const initializeGame = useCallback(async () => {
    try {
      telegram.init()
      const telegramUser = telegram.getUser()

      const userId = getUrlParameter("id") || telegramUser?.id?.toString() || "user123"
      const authKey = getUrlParameter("authKey")
      
      // For development, use a default authKey if not provided
      const finalAuthKey = authKey || "default_auth_key_for_dev"
      
      // Authenticate with PHP backend
      const authResult = await apiService.authenticate({
        userId,
        firstName: telegramUser?.first_name || getUrlParameter("first_name") || "User",
        lastName: telegramUser?.last_name || getUrlParameter("last_name") || "",
        avatarUrl: "",
        referredBy: parseReferralFromUrl() || "",
        refAuth: parseRefAuthFromUrl() || ""
      })
      
      if (!authResult.success) {
        throw new Error('Authentication failed')
      }
      
      // Set auth key for future requests
      apiService.setAuthKey(authResult.authKey)

      if (!authResult.isNewUser && authResult.userData) {
        // Existing user with valid auth
        const existingUser = { 
          ...defaultUserData, 
          ...authResult.userData, 
          id: userId, 
          authKey: authResult.authKey,
          isReturningUser: true 
        }
        
        // Calculate offline mining rewards if user was mining
        if (existingUser.isMining && existingUser.miningStartTime) {
          const now = Date.now()
          const offlineDuration = Math.floor((now - existingUser.miningStartTime) / 1000)
          const limitedDuration = Math.min(offlineDuration, GAME_CONFIG.MAX_MINING_TIME)
          
          if (limitedDuration > 0) {
            const { earned } = gameLogic.calculateMiningRewards(existingUser, limitedDuration)
            existingUser.pendingRewards = earned
          }
        }
        
        setUser(existingUser)
      } else {
        // New user - create account
        const newUser = { 
          ...defaultUserData,
          ...authResult.userData,
          authKey: authResult.authKey,
          isReturningUser: false
        }
        setUser(newUser)
      }

      setGameState((prev) => ({ ...prev, dataLoaded: true }))
      setLoading(false)
    } catch (error) {
      console.error("Failed to initialize game:", error)
      // Stay in loading state on error
    }
  }, [])

  // Start mining
  const startMining = useCallback(() => {
    if (user.isMining) return { success: false, message: "Already mining!" }

    const now = Date.now()
    const updatedUser = {
      ...user,
      isMining: true,
      miningStartTime: now,
      pendingRewards: 0,
    }

    setUser(updatedUser)
    debouncedSaveUserData()
    telegram.hapticFeedback("success")

    return { success: true, message: "Mining started!" }
  }, [user, debouncedSaveUserData])

  // Claim mining rewards
  const claimMiningRewards = useCallback(() => {
    if (!gameLogic.canClaimMining(user)) {
      telegram.hapticFeedback("error")
      return { success: false, message: "Mining time not reached!" }
    }

    const duration = gameLogic.getMiningDuration(user)
    const { earned, type, xp } = gameLogic.calculateMiningRewards(user, duration)

    const updatedUser = {
      ...user,
      balance: user.balance + earned,
      totalEarned: user.totalEarned + earned,
      isMining: false,
      miningStartTime: 0,
      pendingRewards: 0,
      lastClaimTime: Date.now(),
      xp: user.xp + xp,
    }

    setUser(updatedUser)
    debouncedSaveUserData()
    telegram.hapticFeedback("success")

    return { 
      success: true, 
      earned, 
      type, 
      message: `Claimed ${gameLogic.formatNumber(earned)} DRX!` 
    }
  }, [user, debouncedSaveUserData])

  // Upgrade functions
  const upgradeBoost = useCallback(
    async (boostType: "miningSpeed" | "claimTime" | "miningRate") => {
      const currentLevel = user.boosts[`${boostType}Level` as keyof typeof user.boosts]
      const cost = gameLogic.getBoostCost(boostType, currentLevel)

      if (user.balance < cost) {
        telegram.hapticFeedback("error")
        return { success: false, message: `Need ${gameLogic.formatNumber(cost)} DRX` }
      }

      const updates: Partial<User> = {
        balance: user.balance - cost,
        boosts: { ...user.boosts, [`${boostType}Level`]: currentLevel + 1 },
      }

      // Calculate new mining rate and claim time based on all boosts
      const newMiningSpeedLevel = boostType === "miningSpeed" ? currentLevel + 1 : user.boosts.miningSpeedLevel
      const newClaimTimeLevel = boostType === "claimTime" ? currentLevel + 1 : user.boosts.claimTimeLevel
      const newMiningRateLevel = boostType === "miningRate" ? currentLevel + 1 : user.boosts.miningRateLevel
      
      // Update mining rate with combined boosts
      const miningRateMultiplier = Math.pow(GAME_CONFIG.MINING_RATE_MULTIPLIER, (newMiningRateLevel || 1) - 1)
      const miningSpeedMultiplier = Math.pow(GAME_CONFIG.MINING_SPEED_MULTIPLIER, (newMiningSpeedLevel || 1) - 1)
      updates.miningRate = GAME_CONFIG.BASE_MINING_RATE * miningRateMultiplier * miningSpeedMultiplier
      
      // Update claim time
      updates.minClaimTime = Math.max(300, GAME_CONFIG.MIN_CLAIM_TIME - (GAME_CONFIG.CLAIM_TIME_REDUCTION * ((newClaimTimeLevel || 1) - 1)))

      const updatedUser = { ...user, ...updates }
      setUser(updatedUser)
      await apiService.updateUser(updatedUser.id, updatedUser)

      telegram.hapticFeedback("success")
      return { success: true, message: `${boostType} upgraded!` }
    },
    [user],
  )

  // Claim welcome bonus
  const claimWelcomeBonus = useCallback(async () => {
    if (user.bonusClaimed) return { success: false, message: "Already claimed" }

    const updatedUser = {
      ...user,
      balance: user.balance + GAME_CONFIG.WELCOME_BONUS,
      totalEarned: user.totalEarned + GAME_CONFIG.WELCOME_BONUS,
      bonusClaimed: true,
      dataInitialized: true,
    }

    setUser(updatedUser)
    await apiService.updateUser(updatedUser.id, updatedUser)

    telegram.hapticFeedback("success")
    return { success: true, message: `Claimed ${GAME_CONFIG.WELCOME_BONUS} DRX!` }
  }, [user])

  // Mining interval effect
  useEffect(() => {
    // No real-time interval needed - mining works offline
    // Rewards are calculated based on time difference when user returns
  }, [user.isMining, gameState.dataLoaded])

  // Initialize on mount
  useEffect(() => {
    initializeGame()
  }, [initializeGame])

  return {
    user,
    gameState,
    loading,
    startMining,
    claimMiningRewards,
    upgradeBoost,
    claimWelcomeBonus,
    saveUserData: debouncedSaveUserData,
    setUser,
  }
}