import { defineStore } from 'pinia';
import api from '../services/api';

export const useNotificationStore = defineStore('notification', {
  state: () => ({
    notificationsList: [],
    unreadCount: 0,
    loading: false,
    pollTimer: null,
  }),

  actions: {
    async fetchUnreadCount() {
      try {
        const res = await api.get('/notifications/unread-count');
        const data = res.data?.data || res.data;
        if (data && typeof data.unread_count === 'number') {
          this.unreadCount = data.unread_count;
        }
      } catch (e) {
        // Ignore unauthenticated or network errors quietly
      }
    },

    async fetchNotifications() {
      this.loading = true;
      try {
        const res = await api.get('/notifications/low-stock', { params: { per_page: 20 } });
        const data = res.data?.data || res.data;
        if (data) {
          this.notificationsList = data.items || [];
          if (typeof data.unread_count === 'number') {
            this.unreadCount = data.unread_count;
          }
        }
      } catch (e) {
        console.error('Failed to load low stock notifications:', e);
      } finally {
        this.loading = false;
      }
    },

    async markAsRead(notificationId) {
      try {
        const res = await api.post(`/notifications/${notificationId}/read`);
        const data = res.data?.data || res.data;

        const target = this.notificationsList.find(n => n.id === notificationId);
        if (target) {
          target.is_read = true;
        }

        if (data && typeof data.unread_count === 'number') {
          this.unreadCount = data.unread_count;
        } else {
          this.unreadCount = Math.max(0, this.unreadCount - 1);
        }
      } catch (e) {
        console.error('Failed to mark notification as read:', e);
      }
    },

    async markAllAsRead() {
      try {
        await api.post('/notifications/read-all');
        this.notificationsList.forEach(n => {
          n.is_read = true;
        });
        this.unreadCount = 0;
      } catch (e) {
        console.error('Failed to mark all notifications as read:', e);
      }
    },

    startPolling(intervalMs = 30000) {
      this.stopPolling();
      this.fetchUnreadCount();
      this.pollTimer = setInterval(() => {
        this.fetchUnreadCount();
      }, intervalMs);
    },

    stopPolling() {
      if (this.pollTimer) {
        clearInterval(this.pollTimer);
        this.pollTimer = null;
      }
    },
  },
});
