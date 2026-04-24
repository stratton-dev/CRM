import { defineStore, storeToRefs } from 'pinia'
import { useDataStore } from '@/stores/data'
import { useNotificationStore } from '@/stores/notification'
import type { Rank } from '@/types/models'

export const useGamificationStore = defineStore('gamification', () => {
  const data = useDataStore()
  const notify = useNotificationStore()

  const { users, clients } = storeToRefs(data)

  const recalculateRanks = () => {
    const allClients = clients.value

    data.rawUpdateUsers((currentUsers) =>
      currentUsers.map((user) => {
        if (user.role === 'SALES' || user.role === 'MANAGER') {
          const myClients = allClients.filter((client) => client.ownerId === user.id && client.status === 'SIGNED')
          const points = myClients.length * 500

          let rank: Rank = 'JUNIOR'
          if (points >= 5000) rank = 'LEGEND'
          else if (points >= 3000) rank = 'MASTER'
          else if (points >= 1500) rank = 'SENIOR'
          else if (points >= 500) rank = 'REGULAR'

          if (user.points !== points || user.rank !== rank) {
            if (user.rank !== rank) {
              notify.add({
                userId: user.id,
                type: 'INFO',
                message: `Gratulacje! Osiągnąłeś nową rangę: ${rank}.`,
              })
            }
            return { ...user, points, rank }
          }
        }
        return user
      })
    )
  }

  const getNextRankTarget = (currentPoints: number): { nextRank: Rank; required: number } | null => {
    if (currentPoints < 500) return { nextRank: 'REGULAR', required: 500 }
    if (currentPoints < 1500) return { nextRank: 'SENIOR', required: 1500 }
    if (currentPoints < 3000) return { nextRank: 'MASTER', required: 3000 }
    if (currentPoints < 5000) return { nextRank: 'LEGEND', required: 5000 }
    return null
  }

  return { users, clients, recalculateRanks, getNextRankTarget }
})
