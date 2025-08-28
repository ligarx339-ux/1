"use client"

import { useState, useEffect } from "react"
import { useGameState } from "@/hooks/useGameState"
import { useMissions } from "@/hooks/useMissions"
import { Header } from "@/components/Header"
import { MiningSection } from "@/components/MiningSection"
import { BoostSection } from "@/components/BoostSection"
import { MissionSection } from "@/components/MissionSection"
import { WalletSection } from "@/components/WalletSection"
import { FriendsSection } from "@/components/FriendsSection"
import { BottomNavigation } from "@/components/BottomNavigation"
import { WelcomeModal } from "@/components/Modals/WelcomeModal"
import { SettingsModal } from "@/components/Modals/SettingsModal"
import { RankModal } from "@/components/RankModal"
import { apiService } from "@/lib/api"
import { telegram } from "@/lib/telegram"

export default function Home() {
  const { user, gameState, loading, startMining, claimMiningRewards, upgradeBoost, claimWelcomeBonus, setUser } =
    useGameState()
  const { missions, userMissions, startMission, verifyMission, submitPromoCode, claimReward } =
    useMissions(user.id)

  const [activeSection, setActiveSection] = useState("mining")
  const [showWelcomeModal, setShowWelcomeModal] = useState(false)
  const [showSettingsModal, setShowSettingsModal] = useState(false)
  const [showRankModal, setShowRankModal] = useState(false)
  const [botUsername, setBotUsername] = useState("UCCoinUltraBot")

  // Load bot username from Firebase
  useEffect(() => {
    loadBotUsername()
  }, [])

  const loadBotUsername = async () => {
    try {
      const username = await apiService.getBotUsername()
      setBotUsername(username)
    } catch (error) {
      console.error("Failed to load bot username:", error)
    }
  }

  // Show welcome modal for users who haven't claimed bonus
  useEffect(() => {
    if (gameState.dataLoaded && !user.bonusClaimed) {
      setShowWelcomeModal(true)
    }
  }, [gameState.dataLoaded, user.bonusClaimed])

  // Handle welcome bonus claim
  const handleClaimWelcomeBonus = async () => {
    const result = await claimWelcomeBonus()
    if (result.success) {
      setShowWelcomeModal(false)
      telegram.hapticFeedback("success")
    }
    return result
  }

  // Handle boost upgrades
  const handleUpgradeBoost = async (boostType: "miningSpeed" | "claimTime" | "miningRate") => {
    const result = await upgradeBoost(boostType)
    return result
  }

  // Handle mission reward claim
  const handleClaimMissionReward = async (missionId: string) => {
    const result = await claimReward(missionId)
    if (result.success && result.reward) {
      const updatedUser = {
        ...user,
        balance: user.balance + result.reward,
        totalEarned: user.totalEarned + result.reward,
      }
      setUser(updatedUser)
      await apiService.updateUser(updatedUser.id, updatedUser)
      telegram.hapticFeedback("success")
    }
    return result
  }

  // Handle conversion
  const handleConvert = async (categoryId: string, packageId: string, requiredInfo: Record<string, string>) => {
    try {
      const categories = await apiService.getWalletCategories()
      const category = categories.find(c => c.id === categoryId)
      const package_ = category?.packages.find(p => p.id === packageId)
      
      if (!category || !package_) {
        return { success: false, message: "Invalid category or package" }
      }

      if (user.balance < package_.drxCost) {
        telegram.hapticFeedback("error")
        return { success: false, message: "Insufficient DRX balance" }
      }

      const result = await apiService.createConversion(user.id, {
        fromCurrency: "DRX",
        toCurrency: category.name,
        amount: package_.drxCost,
        convertedAmount: package_.amount,
        category: category.name,
        packageType: package_.name,
        packageImage: package_.image || category.image || null,
        requiredInfo,
      })

      if (result.success) {
        const updatedUser = {
          ...user,
          balance: user.balance - package_.drxCost,
        }
        setUser(updatedUser)
        await apiService.updateUser(updatedUser.id, updatedUser)
        telegram.hapticFeedback("success")
        return { success: true, message: "Conversion requested!" }
      }
    } catch (error) {
      console.error("Conversion failed:", error)
      telegram.hapticFeedback("error")
      return { success: false, message: "Conversion failed" }
    }
  }

  // Handle referral link copy with dynamic bot username
  const handleCopyReferralLink = async () => {
    const referralLink = `https://t.me/${botUsername}?start=${user.id}`

    try {
      if (navigator.clipboard) {
        await navigator.clipboard.writeText(referralLink)
      } else {
        const textArea = document.createElement("textarea")
        textArea.value = referralLink
        document.body.appendChild(textArea)
        textArea.select()
        document.execCommand("copy")
        document.body.removeChild(textArea)
      }

      telegram.hapticFeedback("success")

      if (telegram.webApp) {
        const shareText = `🎮 Join DRX Mining and start earning DRX coins!

💎 Get 100 DRX welcome bonus
⛏️ Mine to earn more DRX
🎁 Complete missions for rewards

Join now: ${referralLink}`

        telegram.openLink(
          `https://t.me/share/url?url=${encodeURIComponent(referralLink)}&text=${encodeURIComponent(shareText)}`,
        )
      }

      return { success: true, message: "Link copied and shared!" }
    } catch (error) {
      console.error("Failed to copy referral link:", error)
      telegram.hapticFeedback("error")
      return { success: false, message: "Failed to copy link" }
    }
  }

  // Handle settings update
  const handleUpdateSettings = async (newSettings: Partial<typeof user.settings>) => {
    const updatedUser = {
      ...user,
      settings: { ...user.settings, ...newSettings },
    }
    setUser(updatedUser)
    await apiService.updateUser(updatedUser.id, updatedUser)
  }

  // Prevent section change if welcome bonus not claimed
  const handleSectionChange = (section: string) => {
    if (!user.bonusClaimed && section !== "mining") {
      setShowWelcomeModal(true)
      return
    }
    setActiveSection(section)
  }

  // Show loading screen if not authenticated or loading
  if (loading || !gameState.dataLoaded || !user.authKey) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black overflow-hidden">
        <video
          src="/loading.mp4"
          autoPlay
          loop
          muted
          playsInline
          className="absolute inset-0 w-full h-full object-cover z-0"
        />
        <div className="absolute inset-0 bg-black/20 z-10" />
        <div className="relative z-20 text-center">
          <div className="w-16 h-16 border-4 border-green-500 border-t-transparent rounded-full animate-spin mx-auto mb-4"></div>
          <p className="text-white text-lg font-semibold">Authenticating...</p>
          <p className="text-gray-300 text-sm mt-2">Verifying your credentials</p>
        </div>
      </div>
    )
  }

  return (
    <div
      className="min-h-screen bg-cover bg-center bg-fixed relative overflow-x-hidden"
      style={{ backgroundImage: "url(/background.png)" }}
    >
      {/* Background Overlay */}
      <div className="absolute inset-0 bg-black/40" />

      {/* App Container */}
      <div className="relative z-10 main-container min-h-screen bg-transparent px-2 sm:px-4 md:px-6">
        {/* Header */}
        <Header user={user} onOpenSettings={() => setShowSettingsModal(true)} />

        {/* Main Content */}
        <main className="flex-1 pb-20 sm:pb-24 md:pb-28 flex flex-col">
          {activeSection === "mining" && (
            <MiningSection 
              user={user} 
              onStartMining={startMining} 
              onClaimRewards={claimMiningRewards}
              onOpenRank={() => setShowRankModal(true)} 
            />
          )}

          {activeSection === "boost" && (
            <BoostSection
              user={user}
              onUpgrade={handleUpgradeBoost}
              onOpenRank={() => setShowRankModal(true)}
            />
          )}

          {activeSection === "missions" && (
            <MissionSection
              missions={missions}
              userMissions={userMissions}
              onStartMission={startMission}
              onVerifyMission={verifyMission}
              onSubmitPromoCode={submitPromoCode}
              onClaimReward={handleClaimMissionReward}
            />
          )}

          {activeSection === "wallet" && (
            <WalletSection user={user} onConvert={handleConvert} />
          )}

          {activeSection === "friends" && <FriendsSection user={user} onCopyReferralLink={handleCopyReferralLink} />}
        </main>

        {/* Bottom Navigation */}
        <BottomNavigation activeSection={activeSection} onSectionChange={handleSectionChange} />
      </div>

      {/* Modals */}
      <WelcomeModal
        isOpen={showWelcomeModal}
        onClose={() => setShowWelcomeModal(false)}
        onClaimBonus={handleClaimWelcomeBonus}
        onSkip={async () => {
          const updatedUser = { ...user, bonusClaimed: true, dataInitialized: true }
          setUser(updatedUser)
          setShowWelcomeModal(false)
          await apiService.updateUser(updatedUser.id, updatedUser)
        }}
      />

      <SettingsModal
        isOpen={showSettingsModal}
        onClose={() => setShowSettingsModal(false)}
        user={user}
        onUpdateSettings={handleUpdateSettings}
      />

      <RankModal isOpen={showRankModal} onClose={() => setShowRankModal(false)} user={user} />
    </div>
  )
}