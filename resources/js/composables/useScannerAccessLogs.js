import { ref, reactive, onMounted, onUnmounted, watch } from 'vue'

/**
 * Composable for real-time scanner access logs via WebSocket
 *
 * Usage:
 * ```
 * const { logs, isLive, isConnected, newEntriesCount, filters, toggleLive, loadMore } = useScannerAccessLogs(gymId)
 * ```
 */
export function useScannerAccessLogs(gymId, options = {}) {
    const logs = ref([])
    const isLive = ref(true)
    const isConnected = ref(false)
    const isPaused = ref(false)
    const newEntriesCount = ref(0)
    const isLoading = ref(false)
    const hasMore = ref(true)
    const currentPage = ref(1)

    const filters = reactive({
        scanner: options.scanner || null,
        type: options.type || null,         // 'qr_code' | 'nfc_card'
        task: options.task || null,         // device task, e.g. 'dispenser'
        status: options.status || null,     // 'granted' | 'denied'
        origin: options.origin || null,     // 'home' | 'guest'
        dateFrom: options.dateFrom || null,
        dateTo: options.dateTo || null,
    })

    let channel = null
    let reconnectTimeout = null

    /**
     * Initialize WebSocket connection
     */
    const initializeWebSocket = () => {
        if (!window.Echo || !gymId) {
            console.warn('Echo not available or gymId missing')
            return
        }

        try {
            channel = window.Echo.private(`gym.${gymId}.access-logs`)

            channel.listen('.scanner.access', (event) => {
                handleNewAccessLog(event.log)
            })

            channel.subscribed(() => {
                isConnected.value = true
                console.log(`Subscribed to gym.${gymId}.access-logs`)
            })

            channel.error((error) => {
                console.error('WebSocket error:', error)
                isConnected.value = false
                scheduleReconnect()
            })
        } catch (error) {
            console.error('Failed to initialize WebSocket:', error)
            isConnected.value = false
        }
    }

    /**
     * Handle incoming access log from WebSocket
     */
    const handleNewAccessLog = (log) => {
        // Check if log matches current filters
        if (!matchesFilters(log)) {
            return
        }

        if (isLive.value && !isPaused.value) {
            // Add to beginning of logs
            logs.value.unshift(log)

            // Keep list manageable (max 200 entries in memory)
            if (logs.value.length > 200) {
                logs.value.pop()
            }

            // Highlight new denied entries
            if (!log.access_granted) {
                highlightDeniedEntry(log)
            }
        } else {
            // User paused live mode, just increment counter
            newEntriesCount.value++
        }
    }

    /**
     * Check if log matches current filters
     */
    const matchesFilters = (log) => {
        if (filters.scanner && log.device_number !== filters.scanner) {
            return false
        }
        if (filters.type) {
            if (filters.type === 'qr_code') {
                if (log.scan_type !== 'qr_code' && log.scan_type !== 'rolling_qr') {
                    return false
                }
            } else if (log.scan_type !== filters.type) {
                return false
            }
        }
        if (filters.task && log.device_task !== filters.task) {
            return false
        }
        if (filters.status === 'granted' && !log.access_granted) {
            return false
        }
        if (filters.status === 'denied' && log.access_granted) {
            return false
        }
        if (filters.origin === 'guest' && !log.is_cross_location) {
            return false
        }
        if (filters.origin === 'home' && log.is_cross_location) {
            return false
        }
        return true
    }

    /**
     * Highlight denied entry (visual + optional audio)
     */
    const highlightDeniedEntry = (log) => {
        // Play sound if enabled
        if (options.soundEnabled) {
            playAlertSound()
        }

        // Browser notification for denied access
        if (options.notificationsEnabled && 'Notification' in window && Notification.permission === 'granted') {
            const title = log.nfc_card_id
                ? 'Unbekannte NFC-Karte'
                : 'Zugang verweigert'
            const body = log.denial_reason || 'Ein Scan wurde abgelehnt'

            new Notification(title, {
                // Station entries have no device, so there is nothing to prefix with.
                body: log.scanner_name ? `${log.scanner_name}: ${body}` : body,
                icon: '/favicon.ico',
                tag: `access-denied-${log.id}`,
            })
        }
    }

    /**
     * Play alert sound for denied entries
     */
    const playAlertSound = () => {
        try {
            const audio = new Audio('/sounds/access-denied.mp3')
            audio.volume = 0.3
            audio.play().catch(() => {
                // Ignore autoplay errors
            })
        } catch (e) {
            // Ignore audio errors
        }
    }

    /**
     * Build the query string for the logs endpoint from the active filters.
     */
    const buildFilterParams = (extra = {}) => {
        const params = new URLSearchParams()

        const mapping = {
            scanner: filters.scanner,
            type: filters.type,
            task: filters.task,
            status: filters.status,
            origin: filters.origin,
            date_from: filters.dateFrom,
            date_to: filters.dateTo,
            ...extra,
        }

        Object.entries(mapping).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                params.append(key, value)
            }
        })

        return params.toString()
    }

    /**
     * Toggle live mode on/off
     */
    const toggleLive = () => {
        isPaused.value = !isPaused.value

        if (!isPaused.value && newEntriesCount.value > 0) {
            // Resume live mode - fetch missed entries
            fetchNewEntries()
        }
    }

    /**
     * Fetch new entries that were missed while paused
     */
    const fetchNewEntries = async () => {
        if (isLoading.value) return

        isLoading.value = true
        try {
            const response = await axios.get(route('access-control.logs') + '?' + buildFilterParams())

            if (response.data.data) {
                // Merge new entries
                const existingIds = new Set(logs.value.map(l => l.id))
                const newLogs = response.data.data.filter(l => !existingIds.has(l.id))

                logs.value = [...newLogs, ...logs.value].slice(0, 200)
            }

            newEntriesCount.value = 0
        } catch (error) {
            console.error('Failed to fetch new entries:', error)
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Load more logs (pagination)
     */
    const loadMore = async () => {
        if (isLoading.value || !hasMore.value) return

        isLoading.value = true
        try {
            const response = await axios.get(
                route('access-control.logs') + '?' + buildFilterParams({ page: currentPage.value + 1 })
            )

            if (response.data.data) {
                logs.value.push(...response.data.data)
                currentPage.value++
                hasMore.value = response.data.next_page_url !== null
            }
        } catch (error) {
            console.error('Failed to load more logs:', error)
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Refresh logs with current filters
     */
    const refresh = async () => {
        if (isLoading.value) return

        isLoading.value = true
        currentPage.value = 1
        hasMore.value = true

        try {
            const response = await axios.get(route('access-control.logs') + '?' + buildFilterParams())

            if (response.data.data) {
                logs.value = response.data.data
                hasMore.value = response.data.next_page_url !== null
            }
        } catch (error) {
            console.error('Failed to refresh logs:', error)
        } finally {
            isLoading.value = false
        }
    }

    /**
     * Apply filters and refresh
     */
    const applyFilters = (newFilters) => {
        Object.assign(filters, newFilters)
        refresh()
    }

    /**
     * Clear all filters
     */
    const clearFilters = () => {
        filters.scanner = null
        filters.type = null
        filters.task = null
        filters.status = null
        filters.origin = null
        filters.dateFrom = null
        filters.dateTo = null
        refresh()
    }

    /**
     * Schedule reconnection attempt
     */
    const scheduleReconnect = () => {
        if (reconnectTimeout) {
            clearTimeout(reconnectTimeout)
        }

        reconnectTimeout = setTimeout(() => {
            console.log('Attempting to reconnect WebSocket...')
            initializeWebSocket()
        }, 5000)
    }

    /**
     * Cleanup WebSocket connection
     */
    const cleanup = () => {
        if (channel && window.Echo) {
            window.Echo.leave(`gym.${gymId}.access-logs`)
            channel = null
        }

        if (reconnectTimeout) {
            clearTimeout(reconnectTimeout)
        }

        isConnected.value = false
    }

    /**
     * Initialize logs with provided data
     */
    const setInitialLogs = (initialLogs) => {
        logs.value = initialLogs || []
    }

    // Watch for filter changes
    watch(filters, () => {
        // Reset new entries counter when filters change
        newEntriesCount.value = 0
    }, { deep: true })

    onMounted(() => {
        initializeWebSocket()
    })

    onUnmounted(() => {
        cleanup()
    })

    return {
        logs,
        isLive,
        isConnected,
        isPaused,
        newEntriesCount,
        isLoading,
        hasMore,
        filters,
        toggleLive,
        loadMore,
        refresh,
        applyFilters,
        clearFilters,
        setInitialLogs,
    }
}
