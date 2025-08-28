"use client"

import { useState, useEffect, useCallback } from "react"
import type { Mission, UserMission } from "@/types"
import { apiService } from "@/lib/api"

export const useMissions = (userId: string) => {
  const [missions, setMissions] = useState<Record<string, Mission>>({})
  const [userMissions, setUserMissions] = useState<Record<string, UserMission>>({})
  const [loading, setLoading] = useState(true)

  // Load missions
  const loadMissions = useCallback(async () => {
    try {
      const [globalMissions, userMissionData] = await Promise.all([
        apiService.getMissions(),
        apiService.getUserMissions(userId),
      ])

      setMissions(globalMissions)
      setUserMissions(userMissionData)
    } catch (error) {
      console.error("Failed to load missions:", error)
    } finally {
      setLoading(false)
    }
  }, [userId])

  // Start mission
  const startMission = useCallback(
    async (missionId: string) => {
      const mission = missions[missionId]
      if (!mission) return { success: false, message: "Mission not found" }

      const missionData: UserMission = {
        started: true,
        completed: false,
        claimed: false,
        currentCount: 0,
        startedDate: Date.now(),
        lastVerifyAttempt: Date.now(),
      }

      // Handle different mission types
      switch (mission.type) {
        case "join_channel":
        case "join_group":
          window.open(`https://t.me/${mission.channelId}`, "_blank")
          break
        case "url_timer":
          if (mission.url) {
            window.open(mission.url, "_blank")
            missionData.timerStarted = Date.now()
          }
          break
        case "promo_code":
          if (mission.url) {
            window.open(mission.url, "_blank")
          }
          break
      }

      const result = await apiService.updateUserMission(userId, missionId, missionData)
      const success = result.success
      if (success) {
        setUserMissions((prev) => ({ ...prev, [missionId]: missionData }))
        return { success: true, message: "Mission started!" }
      }

      return { success: false, message: "Failed to start mission" }
    },
    [missions, userId],
  )

  // Verify mission
  const verifyMission = useCallback(
    async (missionId: string) => {
      const mission = missions[missionId]
      const userMission = userMissions[missionId]

      if (!mission || !userMission) {
        return { success: false, message: "Mission not found" }
      }

      let completed = false

      switch (mission.type) {
        case "join_channel":
        case "join_group":
          // Verify Telegram membership
          const verifyResult = await apiService.verifyTelegramMembership(userId, mission.channelId!)
          completed = verifyResult.verified
          break

        case "url_timer":
          // Check if required time has passed
          if (userMission.timerStarted && mission.requiredTime) {
            const timeElapsed = (Date.now() - userMission.timerStarted) / 1000
            completed = timeElapsed >= mission.requiredTime
          }
          break

        case "promo_code":
          // This will be handled by submitPromoCode
          return { success: false, message: "Use promo code submission" }
      }

      if (completed) {
        const updatedMission = {
          ...userMission,
          completed: true,
          completedAt: Date.now(),
          currentCount: mission.requiredCount || 1,
        }

        const result = await apiService.updateUserMission(userId, missionId, updatedMission)
        const success = result.success
        if (success) {
          setUserMissions((prev) => ({ ...prev, [missionId]: updatedMission }))
          return { success: true, message: "Mission completed!" }
        }
      }

      return { success: false, message: "Mission not completed yet" }
    },
    [missions, userMissions, userId],
  )

  // Submit promo code
  const submitPromoCode = useCallback(
    async (missionId: string, code: string) => {
      const mission = missions[missionId]
      const userMission = userMissions[missionId]

      if (!mission || !userMission || mission.type !== "promo_code") {
        return { success: false, message: "Invalid mission" }
      }

      const isCorrect = code.trim().toLowerCase() === mission.code?.toLowerCase()

      if (isCorrect) {
        const updatedMission = {
          ...userMission,
          completed: true,
          completedAt: Date.now(),
          currentCount: 1,
          codeSubmitted: code,
        }

        const result = await apiService.updateUserMission(userId, missionId, updatedMission)
        const success = result.success
        if (success) {
          setUserMissions((prev) => ({ ...prev, [missionId]: updatedMission }))
          return { success: true, message: "Correct code! Mission completed!" }
        }
      }

      return { success: false, message: "Incorrect code. Try again." }
    },
    [missions, userMissions, userId],
  )

  // Claim reward
  const claimReward = useCallback(
    async (missionId: string) => {
      const mission = missions[missionId]
      const userMission = userMissions[missionId]

      if (!mission || !userMission || !userMission.completed || userMission.claimed) {
        return { success: false, message: "Cannot claim reward" }
      }

      const updatedMission = {
        ...userMission,
        claimed: true,
        claimedAt: Date.now(),
      }

      const result = await apiService.updateUserMission(userId, missionId, updatedMission)
      const success = result.success
      if (success) {
        setUserMissions((prev) => ({ ...prev, [missionId]: updatedMission }))
        return { success: true, reward: mission.reward, message: `Claimed ${mission.reward} UC!` }
      }

      return { success: false, message: "Failed to claim reward" }
    },
    [missions, userMissions, userId],
  )

  // Update mission progress
  const updateProgress = useCallback(
    async (missionId: string, increment = 1) => {
      const mission = missions[missionId]
      let userMission = userMissions[missionId]

      if (!mission || !mission.active) return

      // Initialize if not exists
      if (!userMission) {
        userMission = {
          started: false,
          completed: false,
          claimed: false,
          currentCount: 0,
          startedDate: Date.now(),
        }
      }

      if (userMission.completed && userMission.claimed) return

      const newCount = Math.min(userMission.currentCount + increment, mission.requiredCount || 1)
      const completed = newCount >= (mission.requiredCount || 1)

      const updatedMission = {
        ...userMission,
        currentCount: newCount,
        completed,
        completedAt: completed ? Date.now() : userMission.completedAt,
      }

      const result = await apiService.updateUserMission(userId, missionId, updatedMission)
      const success = result.success
      if (success) {
        setUserMissions((prev) => ({ ...prev, [missionId]: updatedMission }))
      }
    },
    [missions, userMissions, userId],
  )

  useEffect(() => {
    if (userId) {
      loadMissions()
    }
  }, [userId, loadMissions])

  return {
    missions,
    userMissions,
    loading,
    startMission,
    verifyMission,
    submitPromoCode,
    claimReward,
    updateProgress,
    loadMissions,
  }
}