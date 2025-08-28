"use client"

import { useState } from "react"
import { X, AlertTriangle } from "lucide-react"

interface WithdrawalModalProps {
  isOpen: boolean
  onClose: () => void
  amount: number
  packageType: string
  pubgId: string
  onConfirm: () => Promise<any>
}

export const WithdrawalModal = ({ isOpen, onClose, amount, packageType, pubgId, onConfirm }: WithdrawalModalProps) => {
  const [confirming, setConfirming] = useState(false)

  const handleConfirm = async () => {
    setConfirming(true)
    try {
      await onConfirm()
      onClose()
    } finally {
      setConfirming(false)
    }
  }

  if (!isOpen) return null

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm">
      <div className="w-full max-w-md mx-4 bg-gray-900/95 backdrop-blur-md border-2 border-orange-500/30 rounded-2xl shadow-xl shadow-orange-500/20">
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b border-gray-700/30">
          <div className="flex items-center gap-4">
            <div className="w-12 h-12 bg-gradient-to-br from-orange-400 to-red-500 rounded-xl flex items-center justify-center text-2xl">
              💸
            </div>
            <div>
              <h2 className="text-xl font-bold text-white font-display">Confirm Withdrawal</h2>
              <p className="text-sm text-gray-400">Please confirm your withdrawal request</p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="w-10 h-10 bg-gray-700/20 hover:bg-red-500/20 border border-gray-700/30 hover:border-red-500/50 rounded-xl flex items-center justify-center text-gray-400 hover:text-red-400 transition-all duration-200"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content */}
        <div className="p-6">
          {/* Warning */}
          <div className="flex items-start gap-3 p-4 bg-orange-500/10 border border-orange-500/20 rounded-xl mb-6">
            <AlertTriangle className="w-5 h-5 text-orange-400 mt-0.5 flex-shrink-0" />
            <div className="text-sm text-orange-200">
              <p className="font-semibold mb-1">Important Notice</p>
              <p>This action cannot be undone. Please verify your PUBG ID is correct before proceeding.</p>
            </div>
          </div>

          {/* Withdrawal Details */}
          <div className="space-y-4 mb-6">
            <div className="flex justify-between items-center p-3 bg-black/20 rounded-xl">
              <span className="text-gray-400">Amount:</span>
              <span className="text-white font-bold">{amount} UC</span>
            </div>
            <div className="flex justify-between items-center p-3 bg-black/20 rounded-xl">
              <span className="text-gray-400">Package:</span>
              <span className="text-white font-bold">{packageType}</span>
            </div>
            <div className="flex justify-between items-center p-3 bg-black/20 rounded-xl">
              <span className="text-gray-400">PUBG ID:</span>
              <span className="text-white font-bold">{pubgId}</span>
            </div>
            <div className="flex justify-between items-center p-3 bg-black/20 rounded-xl">
              <span className="text-gray-400">Processing Time:</span>
              <span className="text-white font-bold">24-48 hours</span>
            </div>
          </div>

          {/* Actions */}
          <div className="space-y-3">
            <button
              onClick={handleConfirm}
              disabled={confirming}
              className="w-full bg-gradient-to-r from-red-500 to-orange-500 hover:from-red-600 hover:to-orange-600 disabled:from-gray-700 disabled:to-gray-800 text-white font-bold py-3 px-4 rounded-xl transition-all duration-200 hover:scale-105 disabled:hover:scale-100 shadow-lg hover:shadow-xl flex items-center justify-center gap-2"
            >
              <span className="text-lg">✅</span>
              {confirming ? "Processing..." : "Confirm Withdrawal"}
            </button>

            <button
              onClick={onClose}
              className="w-full bg-gray-700/20 hover:bg-gray-700/40 text-gray-300 hover:text-white font-semibold py-3 px-4 rounded-xl transition-all duration-200 border border-gray-700/30 hover:border-gray-600/50"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}
