import { registerEditorComponent } from './editor'

// Resolve the saved theme — cookie first (works on every page), then
// localStorage, then OS preference — and apply the <html> class.
function isDarkPreferred() {
    try {
        var m = document.cookie.match(/(?:^|;\s*)theme=(dark|light)/)
        var t = m ? m[1] : localStorage.getItem('theme')
        if (t === 'dark') return true
        if (t === 'light') return false
        return window.matchMedia('(prefers-color-scheme: dark)').matches
    } catch (e) {
        return false
    }
}

function applyTheme() {
    document.documentElement.classList.toggle('dark', isDarkPreferred())
}

// Re-apply after every SPA navigation (wire:navigate swaps the document and
// the no-flash <head> script does NOT re-run), so the theme stays consistent
// across page transitions and browser back/forward.
document.addEventListener('livewire:navigated', function () {
    applyTheme()
    if (window.Alpine && window.Alpine.store('theme')) {
        window.Alpine.store('theme').dark = document.documentElement.classList.contains('dark')
    }
})

document.addEventListener('alpine:init', () => {
    registerEditorComponent(window.Alpine)

    // Light/dark theme. The <html> class is set by an inline no-flash script
    // before paint and re-applied on navigation; this store keeps it in sync
    // and persists changes to both a cookie and localStorage.
    window.Alpine.store('theme', {
        dark: document.documentElement.classList.contains('dark'),
        toggle() {
            this.dark = !this.dark
            document.documentElement.classList.toggle('dark', this.dark)
            var value = this.dark ? 'dark' : 'light'
            try {
                localStorage.setItem('theme', value)
            } catch (e) { /* ignore storage errors */ }
            // Persist to a cookie too so every page — including standalone pages
            // with their own <head> (report cover/output) — applies the same
            // theme consistently before paint.
            document.cookie = 'theme=' + value + ';path=/;max-age=31536000;samesite=lax'
        },
    })

    // Shared live-preview state: the section editor mirrors its content here as
    // the user types, and the preview pane on the report editor reads from it.
    window.Alpine.store('preview', { html: '', title: '' })

    // Two-way scroll sync between the editor cards and their A4 preview pages
    // (desktop only):
    //  - editing/selecting a section scrolls the preview to that page;
    //  - scrolling the preview highlights + aligns the matching editor card.
    window.Alpine.data('scrollSync', () => ({
        _raf: null,
        syncing: false,

        init() {
            // Jump the preview to whichever section is active on load.
            this.$nextTick(() => this.goTo(this.$el.dataset.activeKey))
        },

        // The preview scrolled: only update the highlight on the matching card.
        // The editor column is left untouched so the user can scroll the
        // preview freely without the left side moving.
        onScroll() {
            if (this._raf) {
                return
            }
            this._raf = requestAnimationFrame(() => {
                this._raf = null
                this.spy()
            })
        },

        spy() {
            const preview = this.$refs.preview

            if (!preview || window.innerWidth < 1024) {
                return
            }

            const base = preview.getBoundingClientRect().top
            const pages = preview.querySelectorAll('[data-page-key]')

            let current = null
            pages.forEach((page) => {
                if (page.getBoundingClientRect().top - base <= 80) {
                    current = page
                }
            })
            current = current || pages[0]

            if (current) {
                this.highlight(current.dataset.pageKey)
            }
        },

        // A section was selected/focused: scroll its preview page into view.
        goTo(key) {
            const preview = this.$refs.preview

            if (!key || !preview || window.innerWidth < 1024) {
                return
            }

            const page = preview.querySelector(`[data-page-key="${key}"]`)

            if (!page) {
                return
            }

            this.highlight(key)

            const target = preview.scrollTop + (page.getBoundingClientRect().top - preview.getBoundingClientRect().top) - 12

            if (Math.abs(target - preview.scrollTop) > 2) {
                this.syncing = true
                preview.scrollTo({ top: Math.max(0, target), behavior: 'smooth' })
                // Release the spy guard once the smooth scroll has settled.
                clearTimeout(this._releaseTimer)
                this._releaseTimer = setTimeout(() => { this.syncing = false }, 450)
            }
        },

        highlight(key) {
            const editor = this.$refs.editor

            if (!editor) {
                return
            }

            editor.querySelectorAll('[data-card-key]').forEach((el) => {
                el.classList.toggle('preview-current', el.dataset.cardKey === key)
            })
        },
    }))
})

// Register the PWA service worker so the app is installable and works offline.
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {})
    })
}
