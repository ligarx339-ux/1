"use client"

import { useState, useEffect } from "react"
import type { User, WalletCategory, Conversion } from "@/types"
import { gameLogic } from "@/lib/game-logic"
import { apiService } from "@/lib/api"
import { Wallet, ArrowRight, Clock, CheckCircle, XCircle, ChevronRight } from "lucide-react"
import Image from "next/image"
import { useToast } from "@/hooks/use-toast"

interface WalletSectionProps {
  user: User
  onConvert: (categoryId: string, packageId: string, requiredInfo: Record<string, string>) => Promise<any>
}

export const WalletSection = ({ user, onConvert }: WalletSectionProps) => {
  const [categories, setCategories] = useState<WalletCategory[]>([])
  const [selectedCategory, setSelectedCategory] = useState<WalletCategory | null>(null)
  const [selectedPackage, setSelectedPackage] = useState<string | null>(null)
  const [requiredInfo, setRequiredInfo] = useState<Record<string, string>>({})
  const [loading, setLoading] = useState(true)
  const [showConversionModal, setShowConversionModal] = useState(false)
  const [conversions, setConversions] = useState<Conversion[]>([])
  const { toast } = useToast()

  // Load wallet categories and conversion history from Firebase
  useEffect(() => {
    loadWalletData()
  }, [user.id])

  const loadWalletData = async () => {
    try {
      setLoading(true)
      const [categoriesData, conversionsData] = await Promise.all([
        apiService.getWalletCategories(),
        apiService.getUserConversions(user.id)
      ])
      setCategories(categoriesData)
      setConversions(conversionsData)
    } catch (error) {
      console.error("Failed to load wallet data:", error)
    } finally {
      setLoading(false)
    }
  }

  const handleCategorySelect = (category: WalletCategory) => {
    setSelectedCategory(category)
    setSelectedPackage(null)
    setRequiredInfo({})
  }

  const handlePackageSelect = (packageId: string) => {
    setSelectedPackage(packageId)
    setShowConversionModal(true)
    
    // Initialize required info
    const info: Record<string, string> = {}
    selectedCategory?.requiredFields.forEach(field => {
      info[field.id] = ""
    })
    setRequiredInfo(info)
  }

  const handleConvert = async () => {
    if (!selectedCategory || !selectedPackage) return

    // Validate required fields
    const missingFields = selectedCategory.requiredFields.filter(field => 
      field.required && !requiredInfo[field.id]?.trim()
    )

    if (missingFields.length > 0) {
      toast({
        title: "❌ Missing Information",
        description: `Please fill in: ${missingFields.map(f => f.label).join(', ')}`,
        duration: 3000,
      })
      return
    }

    try {
      const selectedPkg = selectedCategory.packages.find(p => p.id === selectedPackage)
      if (!selectedPkg) return

      const conversionData = {
        fromCurrency: "DRX",
        toCurrency: selectedCategory.name,
        amount: selectedPkg.drxCost,
        convertedAmount: selectedPkg.amount,
        category: selectedCategory.name,
        packageType: selectedPkg.name,
        packageImage: selectedPkg.image || selectedCategory.image || null,
        requiredInfo
      }
      
      const result = await apiService.createConversion(user.id, conversionData)
      if (result.success) {
        toast({
          title: "🔄 Conversion Requested!",
          description: `Your conversion request has been submitted.`,
          duration: 3000,
        })
        setShowConversionModal(false)
        setSelectedCategory(null)
        setSelectedPackage(null)
        // Reload conversion history
        loadWalletData()
      } else {
        toast({
          title: "❌ Conversion Failed",
          description: result.message,
          duration: 3000,
        })
      }
    } catch (error) {
      toast({
        title: "❌ Error",
        description: "Failed to process conversion",
        duration: 3000,
      })
    }
  }

  const getStatusColor = (status: string) => {
    switch (status) {
      case "approved":
        return "text-green-400"
      case "rejected":
        return "text-red-400"
      default:
        return "text-orange-400"
    }
  }

  const getStatusIcon = (status: string) => {
    switch (status) {
      case "approved":
        return <CheckCircle className="w-4 h-4" />
      case "rejected":
        return <XCircle className="w-4 h-4" />
      default:
        return <Clock className="w-4 h-4" />
    }
  }

  if (loading) {
    return (
      <div className="text-center py-8">
        <div className="text-4xl mb-4 animate-spin text-green-400">⏳</div>
        <p className="text-gray-400">Loading wallet...</p>
      </div>
    )
  }

  // Category selection view
  if (!selectedCategory) {
    return (
      <div className="pb-4">
        {/* Section Header */}
        <div className="bg-gradient-to-r from-green-500/10 to-blue-500/10 border border-green-500/30 rounded-xl p-4 mb-6 shadow-lg shadow-green-500/20">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-gradient-to-br from-green-400 to-blue-500 rounded-xl flex items-center justify-center text-2xl shadow-lg">
              💰
            </div>
            <div>
              <h2 className="text-lg font-bold text-white font-display">Wallet & Exchange</h2>
              <p className="text-gray-300 text-sm">Convert DRX to other currencies</p>
              <div className="flex items-center gap-2 mt-2">
                <div className="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse" />
                <span className="text-sm text-green-400 font-semibold">
                  {gameLogic.formatNumber(user.balance)} DRX Available
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* Categories */}
        <div className="space-y-3 mb-6">
          <h3 className="text-lg font-bold text-white font-display">Select Category</h3>
          {categories.map((category) => (
            <button
              key={category.id}
              onClick={() => handleCategorySelect(category)}
              className="w-full bg-black/30 backdrop-blur-md border border-gray-700/30 rounded-xl p-4 hover:border-green-500/40 transition-all duration-300 hover:shadow-lg hover:shadow-green-500/10 text-left"
            >
              <div className="flex items-center gap-4">
                {category.image ? (
                  <Image
                    src={category.image}
                    alt={category.name}
                    width={48}
                    height={48}
                    className="w-12 h-12 rounded-xl object-cover shadow-lg"
                  />
                ) : (
                  <div className="w-12 h-12 bg-gradient-to-br from-green-400 to-blue-500 rounded-xl flex items-center justify-center text-2xl">
                    {conversion.packageImage ? (
                      <Image
                        src={conversion.packageImage}
                        alt={conversion.category}
                        width={24}
                        height={24}
                        className="w-6 h-6 rounded object-cover"
                      />
                    ) : (
                      "💰"
                    )}
                  </div>
                )}
                <div className="flex-1">
                  <h4 className="text-white font-bold">{category.name}</h4>
                  <p className="text-gray-400 text-sm">{category.description}</p>
                </div>
                <ChevronRight className="w-5 h-5 text-gray-400" />
              </div>
            </button>
          ))}
        </div>

        {/* Conversion History */}
        <div>
          <h3 className="text-lg font-bold text-white font-display mb-4">Conversion History</h3>
          <div className="space-y-3">
            {conversions.length === 0 ? (
              <div className="text-center py-8">
                <div className="text-6xl mb-4">🔄</div>
                <h3 className="text-xl font-bold text-white font-display mb-2">No Conversions Yet</h3>
                <p className="text-gray-400">Start converting DRX to other currencies!</p>
              </div>
            ) : (
              conversions
                .sort((a, b) => b.requestedAt - a.requestedAt)
                .map((conversion) => (
                  <div
                    key={conversion.id}
                    className="bg-black/20 backdrop-blur-md border border-gray-700/20 rounded-xl p-4 hover:border-green-500/30 transition-all duration-300"
                  >
                    <div className="flex items-center justify-between mb-3">
                      <div className="flex items-center gap-3">
                        {conversion.packageImage ? (
                          <Image
                            src={conversion.packageImage}
                            alt={conversion.category}
                            width={40}
                            height={40}
                            className="w-10 h-10 rounded-lg object-cover shadow-lg"
                          />
                        ) : (
                          <div className="w-10 h-10 bg-gradient-to-br from-green-400 to-blue-500 rounded-lg flex items-center justify-center text-lg">
                            💰
                          </div>
                        )}
                        <div>
                          <div className="text-white font-bold">
                            {gameLogic.formatNumber(conversion.amount)} DRX → {conversion.convertedAmount} {conversion.toCurrency}
                          </div>
                          <div className="text-sm text-gray-400">{conversion.packageType}</div>
                          {conversion.requiredInfo && Object.entries(conversion.requiredInfo).map(([key, value]) => (
                            <div key={key} className="text-xs text-gray-500">
                              {key.replace(/([A-Z])/g, ' $1').replace(/^./, str => str.toUpperCase())}: {value}
                            </div>
                          ))}
                        </div>
                      </div>
                      <div className={`flex items-center gap-2 font-bold ${getStatusColor(conversion.status)}`}>
                        {getStatusIcon(conversion.status)}
                        <span className="capitalize">{conversion.status}</span>
                      </div>
                    </div>
                    <div className="text-xs text-gray-400">
                      {new Date(conversion.requestedAt).toLocaleString()}
                    </div>
                  </div>
                ))
            )}
          </div>
        </div>
      </div>
    )
  }

  // Package selection view
  return (
    <div className="pb-4">
      {/* Back button and category header */}
      <div className="flex items-center gap-3 mb-4">
        <button
          onClick={() => setSelectedCategory(null)}
          className="bg-gray-700/20 hover:bg-gray-700/40 text-gray-300 hover:text-white font-semibold py-2 px-3 rounded-xl transition-all duration-200 border border-gray-700/30 hover:border-gray-600/50"
        >
          ← Back
        </button>
        <div className="flex-1">
          <h2 className="text-lg font-bold text-white font-display">{selectedCategory.name}</h2>
          <p className="text-gray-400 text-sm">{selectedCategory.description}</p>
        </div>
      </div>

      {/* Packages */}
      <div className="grid grid-cols-2 gap-4 mb-6">
        {selectedCategory.packages.map((pkg) => (
          <button
            key={pkg.id}
            onClick={() => handlePackageSelect(pkg.id)}
            disabled={user.balance < pkg.drxCost}
            className={`bg-black/30 backdrop-blur-md border rounded-2xl p-4 text-center transition-all duration-300 hover:shadow-lg hover:scale-105 relative ${
              pkg.popular
                ? "border-yellow-500/50 shadow-yellow-500/20 bg-yellow-500/5"
                : "border-gray-700/30 hover:border-green-500/40 hover:shadow-green-500/10"
            } ${
              user.balance < pkg.drxCost ? "opacity-50 cursor-not-allowed" : "cursor-pointer"
            }`}
          >
            {pkg.popular && (
              <div className="absolute -top-2 left-1/2 transform -translate-x-1/2 bg-gradient-to-r from-yellow-400 to-orange-500 text-white px-3 py-1 rounded-full text-xs font-bold shadow-md">
                POPULAR
              </div>
            )}
            {pkg.image && (
              <Image
                src={pkg.image}
                alt={pkg.name}
                width={48}
                height={48}
                className="w-12 h-12 rounded-xl object-cover mx-auto mb-3 shadow-lg"
              />
            )}
            <div className="text-xl font-bold text-white mb-1">{pkg.amount}</div>
            <div className="text-sm text-gray-400 font-semibold uppercase tracking-wide mb-3">
              {selectedCategory.name}
            </div>
            <div className="text-lg font-bold text-orange-400 mb-2">
              {gameLogic.formatNumber(pkg.drxCost)} DRX
            </div>
            {pkg.bonus && (
              <div className="text-xs text-green-400 font-semibold">+{pkg.bonus} Bonus</div>
            )}
          </button>
        ))}
      </div>

      {/* Conversion Modal */}
      {showConversionModal && selectedPackage && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm">
          <div className="w-full max-w-md mx-4 bg-gray-900/95 backdrop-blur-md border-2 border-green-500/30 rounded-2xl shadow-xl shadow-green-500/20">
            {/* Header */}
            <div className="p-4 border-b border-gray-700/30">
              <h3 className="text-lg font-bold text-white font-display">Confirm Conversion</h3>
              <p className="text-gray-400 text-sm">Fill required information</p>
            </div>

            {/* Content */}
            <div className="p-4">
              {/* Required Fields */}
              <div className="space-y-4 mb-6">
                {selectedCategory.requiredFields.map((field) => (
                  <div key={field.id}>
                    <label className="block text-gray-300 text-sm mb-1">
                      {field.label} {field.required && <span className="text-red-400">*</span>}
                    </label>
                    <input
                      type={field.type}
                      value={requiredInfo[field.id] || ""}
                      onChange={(e) => setRequiredInfo(prev => ({ ...prev, [field.id]: e.target.value }))}
                      placeholder={field.placeholder}
                      className="w-full bg-gray-800/50 border border-gray-700/50 focus:border-green-500 rounded-xl px-3 py-2 text-white placeholder-gray-400 focus:outline-none transition-all duration-200"
                    />
                    {field.helpText && (
                      <p className="text-xs text-gray-500 mt-1">{field.helpText}</p>
                    )}
                  </div>
                ))}
              </div>

              {/* Conversion Details */}
              <div className="bg-black/20 rounded-xl p-4 mb-6">
                <div className="flex justify-between items-center mb-2">
                  <span className="text-gray-400">You pay:</span>
                  <span className="text-white font-bold">
                    {gameLogic.formatNumber(selectedCategory.packages.find(p => p.id === selectedPackage)?.drxCost || 0)} DRX
                  </span>
                </div>
                <div className="flex justify-between items-center">
                  <span className="text-gray-400">You get:</span>
                  <span className="text-green-400 font-bold">
                    {selectedCategory.packages.find(p => p.id === selectedPackage)?.amount} {selectedCategory.name}
                  </span>
                </div>
              </div>

              {/* Actions */}
              <div className="space-y-3">
                <button
                  onClick={handleConvert}
                  disabled={!selectedCategory.requiredFields.every(field => 
                    field.required ? requiredInfo[field.id]?.trim() : true
                  )}
                  className="w-full bg-gradient-to-r from-green-400 to-blue-500 hover:from-green-500 hover:to-blue-600 disabled:from-gray-700 disabled:to-gray-800 disabled:cursor-not-allowed text-white font-bold py-3 px-4 rounded-xl transition-all duration-200 hover:scale-105 disabled:hover:scale-100 shadow-lg hover:shadow-xl"
                >
                  Confirm Conversion
                </button>
                <button
                  onClick={() => setShowConversionModal(false)}
                  className="w-full bg-gray-700/20 hover:bg-gray-700/40 text-gray-300 hover:text-white font-semibold py-3 px-4 rounded-xl transition-all duration-200 border border-gray-700/30 hover:border-gray-600/50"
                >
                  Cancel
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}