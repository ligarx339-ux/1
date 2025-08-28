export interface User {
  id: string
  firstName: string
  lastName?: string
  avatarUrl?: string
  balance: number // DRX balance
  ucBalance: number // UC balance after conversion
  energyLimit: number
  multiTapValue: number
  rechargingSpeed: number
  tapBotPurchased: boolean
  tapBotActive: boolean
  bonusClaimed: boolean
  pubgId?: string
  totalTaps: number
  totalEarned: number
  lastJackpotTime: number
  referredBy?: string
  referralCount: number
  level: number
  xp: number
  streak: number
  combo: number
  lastTapTime: number
  // Mining specific
  isMining: boolean
  miningStartTime: number
  lastClaimTime: number
  pendingRewards: number
  miningRate: number // DRX per second
  minClaimTime: number // minimum mining time before claim (in seconds)
  settings: {
    sound: boolean
    vibration: boolean
    notifications: boolean
  }
  boosts: {
    miningSpeedLevel: number // replaces multiTapLevel
    claimTimeLevel: number // replaces energyLevel
    miningRateLevel: number // replaces rechargeLevel
  }
  missions: Record<string, UserMission>
  withdrawals: Withdrawal[]
  conversions: Conversion[]
  joinedAt: number
  lastActive: number
  isReturningUser: boolean
  dataInitialized: boolean
  authKey?: string
}

export interface Conversion {
  id: string
  fromCurrency: string
  toCurrency: string
  amount: number
  convertedAmount: number
  category: string
  packageType: string
  packageImage?: string
  status: "pending" | "approved" | "rejected"
  requestedAt: number
  requiredInfo: Record<string, string>
  completedAt?: number
}

export interface WalletCategory {
  id: string
  name: string
  description: string
  icon: string
  image?: string
  active: boolean
  requiredFields: WalletRequiredField[]
  packages: WalletPackage[]
  conversionRate: number // DRX to target currency
  minConversion: number
  maxConversion: number
  processingTime: string
  instructions?: string
}

export interface WalletRequiredField {
  id: string
  name: string
  label: string
  type: "text" | "number" | "email"
  placeholder: string
  required: boolean
  validation?: string
  helpText?: string
}

export interface WalletPackage {
  id: string
  name: string
  amount: number
  drxCost: number
  popular: boolean
  bonus?: number
  description?: string
  image?: string
}

export interface Mission {
  id: string
  title: string
  description: string
  reward: number
  requiredCount?: number
  channelId?: string
  url?: string
  code?: string
  requiredTime?: number
  active: boolean
  category: string
  type: "join_channel" | "join_group" | "url_timer" | "promo_code" | "daily_taps" | "invite_friends"
  icon?: string
  img?: string
  priority?: number
  createdAt: string
  expiresAt?: string
  resetDaily?: boolean
  detailedDescription?: string
  instructions?: string[]
  tips?: string[]
}

export interface UserMission {
  started: boolean
  completed: boolean
  claimed: boolean
  currentCount: number
  startedDate?: number
  completedAt?: number
  claimedAt?: number
  lastVerifyAttempt?: number
  timerStarted?: number
  codeSubmitted?: string
}

export interface Withdrawal {
  txId: number
  amount: number
  packageType: string
  status: "pending" | "approved" | "rejected"
  requestedAt: number
  pubgId: string
}

export interface GameState {
  isPlaying: boolean
  tapBotInterval?: NodeJS.Timeout
  rechargeInterval?: NodeJS.Timeout
  comboTimer?: NodeJS.Timeout
  soundEnabled: boolean
  vibrationEnabled: boolean
  dataLoaded: boolean
  saveInProgress: boolean
  lastSaveTime: number
  miningInterval?: NodeJS.Timeout
}

export interface TelegramUser {
  id: number
  first_name: string
  last_name?: string
  username?: string
  photo_url?: string
}