import { Previewer } from 'pagedjs'

/**
 * Paginates the report output into A4 pages with Paged.js, then numbers the
 * pages directly: front matter in lower-roman (starting at the value in
 * window.reportRomanStart — "i" for TU, "ii" elsewhere) and the body restarting
 * at 1 from the first section. Paged.js cannot reliably restart its own `page`
 * counter mid-document, so the numbering is applied here instead.
 */
function toRoman(value) {
    const numerals = [[10, 'x'], [9, 'ix'], [5, 'v'], [4, 'iv'], [1, 'i']]
    let remaining = value
    let result = ''

    for (const [amount, symbol] of numerals) {
        while (remaining >= amount) {
            result += symbol
            remaining -= amount
        }
    }

    return result
}

/**
 * Add a hover "Edit this page" button to each rendered page so the owner can
 * jump straight into edit mode anchored to that page's block. No-op unless
 * window.reportEditUrl is set (owner viewing, not a sample/print).
 */
function addEditButtons(container) {
    const editUrl = window.reportEditUrl
    if (!editUrl) {
        return
    }

    container.querySelectorAll('.pagedjs_page').forEach((page) => {
        const box = page.querySelector('.pagedjs_pagebox') || page

        // Resolve which editable block this page maps to, so the link lands
        // there in edit mode. Cover → cover; front matter → its data-block;
        // body/back → the heading id (sec-/back-).
        let anchor = ''
        if (page.querySelector('.report-cover')) {
            anchor = 'edit-target-cover'
        } else {
            const front = page.querySelector('.report-frontmatter[data-block]')
            const heading = page.querySelector('.report-section [id^="sec-"], .report-section [id^="back-"], .report-backmatter [id]')
            if (front) {
                anchor = 'edit-target-block-' + front.getAttribute('data-block')
            } else if (heading) {
                anchor = 'edit-target-' + heading.id
            }
        }

        const href = anchor ? editUrl + '#' + anchor : editUrl

        const btn = document.createElement('a')
        btn.className = 'page-edit-btn'
        btn.href = href
        btn.setAttribute('contenteditable', 'false')
        btn.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg><span>Edit this page</span>'

        box.style.position = box.style.position || 'relative'
        box.appendChild(btn)
    })
}

function numberPages(container, align, margins) {
    const pages = container.querySelectorAll('.pagedjs_page')

    // Place the page number halfway down the bottom margin and inset it by the
    // configured left/right margins so it lines up with the body content.
    const bottomIn = (margins && Number(margins.bottom)) || 1
    const leftIn = (margins && Number(margins.left)) || 1
    const rightIn = (margins && Number(margins.right)) || 1
    const bottomOffset = `${(bottomIn / 2).toFixed(2)}in`
    const paddingLeft = `${leftIn.toFixed(2)}in`
    const paddingRight = `${rightIn.toFixed(2)}in`

    // The first numbered front page gets `romanStart` (1 = "i", 2 = "ii").
    // It is incremented before use, so seed it one below the desired start.
    const romanStart = (window.reportRomanStart === 1 || window.reportRomanStart === 2)
        ? window.reportRomanStart
        : 2
    let romanNumber = romanStart - 1
    let bodyNumber = 0

    // Map every anchor id to the page label it lands on, so the Table of
    // Contents shows the same number printed on the page (body restarts at 1)
    // rather than Paged.js's absolute page counter.
    const idToLabel = {}

    pages.forEach((page) => {
        // The cover carries no page number.
        if (page.querySelector('.report-cover')) {
            return
        }

        const isBody = page.querySelector('.report-section') !== null
        const isFront = page.querySelector('.report-frontmatter') !== null

        // Skip phantom/blank pages that carry no real content so the body
        // numbering starts at 1 on the first section.
        if (!isBody && !isFront) {
            return
        }

        let label
        if (isBody) {
            bodyNumber += 1
            label = String(bodyNumber)
        } else {
            romanNumber += 1
            label = toRoman(romanNumber)
        }

        page.querySelectorAll('[id]').forEach((el) => {
            if (el.id) {
                idToLabel[el.id] = label
            }
        })

        const box = page.querySelector('.pagedjs_pagebox') || page
        box.style.position = 'relative'

        const number = document.createElement('div')
        number.className = 'report-page-number'
        number.textContent = label
        number.style.cssText =
            `position:absolute;bottom:${bottomOffset};left:0;right:0;`
            + `padding:0 ${paddingRight} 0 ${paddingLeft};`
            + `text-align:${align};font-family:"Times New Roman",Times,serif;`
            + 'font-size:11pt;color:#111;'
        box.appendChild(number)
    })

    // Fill each Table of Contents / Tables / Figures entry with the resolved
    // page label for its target.
    container.querySelectorAll('.toc-entry a[href^="#"]').forEach((link) => {
        const id = link.getAttribute('href').slice(1)
        let pageno = link.querySelector('.toc-pageno')

        if (!pageno) {
            pageno = document.createElement('span')
            pageno.className = 'toc-pageno'
            link.appendChild(pageno)
        }

        pageno.textContent = idToLabel[id] || ''
    })
}

/**
 * Shrink the rendered A4 pages to fit narrow (mobile) viewports so the preview
 * is readable without horizontal scrolling. Uses `zoom` so the layout box
 * resizes too; print resets it via CSS.
 */
function fitToWidth(container) {
    container.style.zoom = ''

    const page = container.querySelector('.pagedjs_page')
    if (!page) {
        return
    }

    const available = document.documentElement.clientWidth - 16
    const pageWidth = page.offsetWidth

    if (pageWidth > 0 && pageWidth > available) {
        container.style.zoom = (available / pageWidth).toFixed(4)
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    const source = document.getElementById('report-source')
    const target = document.getElementById('report-render')

    if (!source || !target) {
        return
    }

    document.body.classList.add('is-paginating')

    try {
        const stylesheets = [...(window.reportStylesheets || [])]

        // Per-report margin + line-spacing rules: turn the inline CSS string
        // into a Blob URL so Paged.js can load it as if it were a real file.
        if (typeof window.reportInlineCss === 'string' && window.reportInlineCss.trim() !== '') {
            const blob = new Blob([window.reportInlineCss], { type: 'text/css' })
            stylesheets.push(URL.createObjectURL(blob))
        }

        const previewer = new Previewer()
        await previewer.preview(source.innerHTML, stylesheets, target)
        numberPages(target, window.reportPageAlign || 'right', window.reportPageMargins)
        addEditButtons(target)
        fitToWidth(target)

        let resizeTimer
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer)
            resizeTimer = setTimeout(() => fitToWidth(target), 150)
        })
    } catch (error) {
        console.error('Paged.js failed to render the report:', error)
        target.innerHTML =
            '<div style="max-width:640px;margin:60px auto;padding:24px;border:1px solid #fca5a5;'
            + 'background:#fef2f2;color:#991b1b;border-radius:8px;font-family:system-ui,sans-serif;font-size:14px">'
            + '<strong>The report could not be paginated.</strong>'
            + '<p style="margin:8px 0 0">' + (error && error.message ? error.message : String(error)) + '</p>'
            + '</div>'
    } finally {
        source.remove()
        document.body.classList.remove('is-paginating')
        document.body.classList.add('is-paginated')
    }
})
