// PHP Backend API Service
const API_BASE_URL = process.env.NODE_ENV === 'production' 
  ? 'https://your-domain.com/backend/api.php' 
  : 'http://localhost:8000/backend/api.php'

class APIService {
  private authKey: string = ''
  
  setAuthKey(authKey: string) {
    this.authKey = authKey
  }
  
  private async request(endpoint: string, options: RequestInit = {}) {
    const url = `${API_BASE_URL}?path=${endpoint}`
    
    const headers = {
      'Content-Type': 'application/json',
      'X-Auth-Key': this.authKey,
      ...options.headers
    }
    
    try {
      const response = await fetch(url, {
        ...options,
        headers
      })
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }
      
      return await response.json()
    } catch (error) {
      console.error('API Request failed:', error)
      throw error
    }
  }
  
  // Authentication
  async authenticate(userData: {
    userId: string
    firstName: string
    lastName?: string
    avatarUrl?: string
    referredBy?: string
  }) {
    return this.request('auth', {
      method: 'POST',
      body: JSON.stringify(userData)
    })
  }
  
  // User operations
  async getUser(userId: string) {
    return this.request(`user&userId=${userId}`)
  }
  
  async updateUser(userId: string, userData: any) {
    return this.request(`user&userId=${userId}`, {
      method: 'PUT',
      body: JSON.stringify(userData)
    })
  }
  
  // Mission operations
  async getMissions() {
    return this.request('missions')
  }
  
  async getUserMissions(userId: string) {
    return this.request(`user-missions&userId=${userId}`)
  }
  
  async updateUserMission(userId: string, missionId: string, missionData: any) {
    return this.request(`user-missions&userId=${userId}`, {
      method: 'PUT',
      body: JSON.stringify({ missionId, missionData })
    })
  }
  
  // Referral operations
  async getReferralData(userId: string) {
    return this.request(`referrals&userId=${userId}`)
  }
  
  // Conversion operations
  async getUserConversions(userId: string) {
    return this.request(`conversions&userId=${userId}`)
  }
  
  async createConversion(userId: string, conversionData: any) {
    return this.request(`conversions&userId=${userId}`, {
      method: 'POST',
      body: JSON.stringify(conversionData)
    })
  }
  
  // Config operations
  async getConfig() {
    return this.request('config')
  }
  
  async getBotUsername() {
    const config = await this.getConfig()
    return config.botUsername || 'UCCoinUltraBot'
  }
  
  async getBannerUrl() {
    const config = await this.getConfig()
    return config.bannerUrl || 'https://mining-master.onrender.com//assets/banner-BH8QO14f.png'
  }
  
  // Wallet operations
  async getWalletCategories() {
    return this.request('wallet-categories')
  }
  
  // Leaderboard operations
  async getGlobalLeaderboard(type: 'balance' | 'level' = 'balance') {
    return this.request(`leaderboard&type=${type}`)
  }
  
  async getLeaderboard() {
    const referrals = await this.request('referrals')
    // Process referral leaderboard data
    return Object.entries(referrals).map(([id, data]: [string, any]) => ({
      id,
      count: data.count || 0,
      earned: data.totalUC || 0,
      user: { firstName: data.firstName || 'User' }
    })).sort((a, b) => b.count - a.count).slice(0, 100)
  }
  
  // Telegram verification
  async verifyTelegramMembership(userId: string, channelId: string) {
    return this.request(`verify-telegram&userId=${userId}&channelId=${encodeURIComponent(channelId)}`)
  }
}

export const apiService = new APIService()