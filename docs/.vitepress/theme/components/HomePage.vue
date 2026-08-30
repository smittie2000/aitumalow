<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { createHighlighter } from 'shiki'

const activeTab = ref('trigger')
const highlighter = ref(null)

// --- Interactive grid background ---
const gridCanvas = ref(null)
let gridCtx = null
let mouseX = -1000
let mouseY = -1000
let gridAnimFrame = null
const GRID_SIZE = 40
const GLOW_RADIUS = 200

function drawGrid() {
  const canvas = gridCanvas.value
  if (!canvas || !gridCtx) return

  const dpr = window.devicePixelRatio || 1
  const w = window.innerWidth
  const h = window.innerHeight

  if (canvas.width !== w * dpr || canvas.height !== h * dpr) {
    canvas.width = w * dpr
    canvas.height = h * dpr
    canvas.style.width = w + 'px'
    canvas.style.height = h + 'px'
    gridCtx.setTransform(dpr, 0, 0, dpr, 0, 0)
  }

  gridCtx.clearRect(0, 0, w, h)

  const isDark = document.documentElement.classList.contains('dark')
  const baseAlpha = isDark ? 0.025 : 0.035
  const glowAlpha = isDark ? 0.3 : 0.22
  const lineColor = isDark ? '255,255,255' : '0,0,0'

  // Draw grid segments — only glow near cursor, not full line
  // Vertical line segments
  for (let x = 0; x <= w; x += GRID_SIZE) {
    for (let y = 0; y < h; y += GRID_SIZE) {
      const segCx = x
      const segCy = y + GRID_SIZE / 2
      const dx = segCx - mouseX
      const dy = segCy - mouseY
      const dist = Math.sqrt(dx * dx + dy * dy)
      const glow = Math.max(0, 1 - dist / GLOW_RADIUS)
      const alpha = baseAlpha + (glowAlpha - baseAlpha) * glow * glow

      gridCtx.beginPath()
      gridCtx.strokeStyle = `rgba(${lineColor}, ${alpha})`
      gridCtx.lineWidth = 0.5
      gridCtx.moveTo(x, y)
      gridCtx.lineTo(x, Math.min(y + GRID_SIZE, h))
      gridCtx.stroke()
    }
  }

  // Horizontal line segments
  for (let y = 0; y <= h; y += GRID_SIZE) {
    for (let x = 0; x < w; x += GRID_SIZE) {
      const segCx = x + GRID_SIZE / 2
      const segCy = y
      const dx = segCx - mouseX
      const dy = segCy - mouseY
      const dist = Math.sqrt(dx * dx + dy * dy)
      const glow = Math.max(0, 1 - dist / GLOW_RADIUS)
      const alpha = baseAlpha + (glowAlpha - baseAlpha) * glow * glow

      gridCtx.beginPath()
      gridCtx.strokeStyle = `rgba(${lineColor}, ${alpha})`
      gridCtx.lineWidth = 0.5
      gridCtx.moveTo(x, y)
      gridCtx.lineTo(Math.min(x + GRID_SIZE, w), y)
      gridCtx.stroke()
    }
  }

  // Intersection dots near cursor
  for (let x = 0; x <= w; x += GRID_SIZE) {
    for (let y = 0; y <= h; y += GRID_SIZE) {
      const dx = x - mouseX
      const dy = y - mouseY
      const dist = Math.sqrt(dx * dx + dy * dy)
      if (dist < GLOW_RADIUS) {
        const glow = 1 - dist / GLOW_RADIUS
        gridCtx.beginPath()
        gridCtx.fillStyle = `rgba(99, 102, 241, ${glow * 0.4})`
        gridCtx.arc(x, y, 1.5 + glow * 1.5, 0, Math.PI * 2)
        gridCtx.fill()
      }
    }
  }
}

function onGridMouseMove(e) {
  const canvas = gridCanvas.value
  if (!canvas) return
  const rect = canvas.getBoundingClientRect()
  mouseX = e.clientX - rect.left
  mouseY = e.clientY - rect.top
  if (!gridAnimFrame) {
    gridAnimFrame = requestAnimationFrame(() => {
      drawGrid()
      gridAnimFrame = null
    })
  }
}

function onGridMouseLeave() {
  mouseX = -1000
  mouseY = -1000
  drawGrid()
}

const tabs = [
  { id: 'trigger', label: 'Trigger' },
  { id: 'action', label: 'Action' },
  { id: 'connect', label: 'Connect' },
]

const codeExamples = {
  trigger: `use App\\Models\\Order;

// Define what starts the workflow
$trigger = $workflow->addNode(
    'Order Placed',
    'model_event',
    [
        'model'  => Order::class,
        'events' => ['created'],
    ]
);`,
  action: `// Add actions that run automatically
$sendMail = $workflow->addNode(
    'Confirm Order',
    'send_mail',
    [
        'to'      => '{{ item.email }}',
        'subject' => 'Order #{{ item.id }} confirmed',
        'body'    => 'Thanks for your purchase!',
    ]
);

$notify = $workflow->addNode(
    'Notify Team',
    'send_notification',
    [
        'notification_class'  => NewOrderNotification::class,
        'notifiable_class'    => User::class,
        'notifiable_id'       => '{{ item.user_id }}',
    ]
);`,
  connect: `// Wire nodes together & activate
$trigger->connect($sendMail);
$sendMail->connect($notify);

$workflow->activate();

// That's it — every new order triggers
// a confirmation email + team notification.`,
}

onMounted(async () => {
  highlighter.value = await createHighlighter({
    themes: ['github-dark', 'github-light'],
    langs: ['php'],
  })

  if (gridCanvas.value) {
    gridCtx = gridCanvas.value.getContext('2d')
    drawGrid()
    window.addEventListener('resize', drawGrid)
    document.addEventListener('mousemove', onGridMouseMove)
    document.addEventListener('mouseleave', onGridMouseLeave)
  }
})

onUnmounted(() => {
  window.removeEventListener('resize', drawGrid)
  if (gridAnimFrame) cancelAnimationFrame(gridAnimFrame)
  document.removeEventListener('mousemove', onGridMouseMove)
  document.removeEventListener('mouseleave', onGridMouseLeave)
})

const highlightedCode = computed(() => {
  if (!highlighter.value) return codeExamples[activeTab.value]
  return highlighter.value.codeToHtml(codeExamples[activeTab.value], {
    lang: 'php',
    themes: { light: 'github-light', dark: 'github-dark' },
  })
})
</script>

<template>
  <div class="home-page">
    <!-- Interactive grid background -->
    <canvas ref="gridCanvas" class="grid-bg"></canvas>

    <!-- Hero -->
    <section class="hero">
      <div class="hero-content">
        <div class="hero-badge">Open Source Laravel Package</div>
        <h1 class="hero-title">
          Automate anything.<br>
          <span class="hero-highlight">Deploy nothing.</span>
        </h1>
        <p class="hero-description">
          Chain triggers, conditions, loops, AI calls, and actions into
          multi-step workflows — visually or in PHP. Ship new business logic
          without changing a single controller.
        </p>
        <div class="hero-actions">
          <a href="/getting-started/installation" class="btn btn-primary">Get started</a>
          <a href="/getting-started/quick-start" class="btn btn-secondary">Quick start →</a>
        </div>
      </div>
    </section>

    <!-- Screenshot (browser mock) -->
    <section class="screenshot-section">
      <div class="screenshot-container">
        <div class="browser-chrome">
          <div class="browser-dots">
            <span class="dot dot-red"></span>
            <span class="dot dot-yellow"></span>
            <span class="dot dot-green"></span>
          </div>
          <div class="browser-nav">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
          </div>
          <div class="browser-address">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <span>myapp.test/workflow-editor</span>
          </div>
        </div>
        <img class="screenshot-dark" src="/screenshots/workflow-editor.png" alt="Visual Workflow Editor" />
        <img class="screenshot-light" src="/screenshots/workflow-editor-light.png" alt="Visual Workflow Editor" />
      </div>
    </section>

    <!-- Animated Edge Divider -->
    <div class="edge-divider">
      <svg class="edge-svg" viewBox="0 0 200 80" preserveAspectRatio="none">
        <path
          d="M 100 0 L 100 80"
          class="edge-path-bg"
        />
        <path
          d="M 100 0 L 100 80"
          class="edge-path"
        />
      </svg>
      <div class="edge-label">
        <span>or define it in code</span>
      </div>
    </div>

    <!-- Code Example -->
    <section class="code-section">
      <div class="code-container">
        <div class="code-header">
          <div class="code-tabs">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              :class="['code-tab', { active: activeTab === tab.id }]"
              @click="activeTab = tab.id"
            >
              {{ tab.label }}
            </button>
          </div>
          <div class="code-filename">routes/console.php</div>
        </div>
        <div class="code-body" v-html="highlightedCode"></div>
      </div>
    </section>

    <!-- Features -->
    <section class="features-section">
      <div class="features-header">
        <h2>Everything you need to automate</h2>
        <p>26 ready-to-use nodes. Visual editor. AI integration. Zero external dependencies.</p>
      </div>

      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
          </div>
          <h3>26 Built-in Nodes</h3>
          <p>Email, HTTP, AI, delays, conditions, loops, sub-workflows — connect them like building blocks.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
          </div>
          <h3>Visual Editor</h3>
          <p>Drag-and-drop workflow builder with React Flow. Design complex automations without writing code.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 18V5"/><path d="M15 13a4.17 4.17 0 0 1-3-4 4.17 4.17 0 0 1-3 4"/><path d="M17.598 6.5A3 3 0 1 0 12 5a3 3 0 1 0-5.598 1.5"/><path d="M17.997 5.125a4 4 0 0 1 2.526 5.77"/><path d="M18 18a4 4 0 0 0 2-7.464"/><path d="M19.967 17.483A4 4 0 1 1 12 18a4 4 0 1 1-7.967-.517"/><path d="M6 18a4 4 0 0 1-2-7.464"/><path d="M6.003 5.125a4 4 0 0 0-2.526 5.77"/></svg>
          </div>
          <h3>AI Node</h3>
          <p>Connect any LLM. Classify, summarize, extract, generate — AI becomes just another node in your flow.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
          </div>
          <h3>Reliable Execution</h3>
          <p>BFS graph traversal with automatic retries and backoff. Pause, resume, and pick up right where you left off.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
          </div>
          <h3>REST API</h3>
          <p>Create, edit, run, and monitor workflows from any frontend or AI agent. Complete CRUD + execution endpoints.</p>
        </div>

        <div class="feature-card">
          <div class="feature-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.39 4.39a1 1 0 0 0 1.68-.474 2.5 2.5 0 1 1 3.014 3.015 1 1 0 0 0-.474 1.68l1.683 1.682a2.414 2.414 0 0 1 0 3.414L19.61 15.39a1 1 0 0 1-1.68-.474 2.5 2.5 0 1 0-3.014 3.015 1 1 0 0 1 .474 1.68l-1.683 1.682a2.414 2.414 0 0 1-3.414 0L8.61 19.61a1 1 0 0 0-1.68.474 2.5 2.5 0 1 1-3.014-3.015 1 1 0 0 0 .474-1.68l-1.683-1.682a2.414 2.414 0 0 1 0-3.414L4.39 8.61a1 1 0 0 1 1.68.474 2.5 2.5 0 1 0 3.014-3.015 1 1 0 0 1-.474-1.68l1.683-1.682a2.414 2.414 0 0 1 3.414 0z"/></svg>
          </div>
          <h3>Plugin System</h3>
          <p>Bundle custom nodes, middleware, and event listeners into reusable plugins. Share across projects.</p>
        </div>
      </div>
    </section>

    <!-- Why Section -->
    <section class="why-section">
      <div class="why-header">
        <div class="why-badge">Why this package?</div>
        <h2>Your Laravel app becomes an<br>automation platform</h2>
        <p class="why-subtitle">
          Most teams hardcode business logic into controllers, jobs, and event listeners.
          When requirements change, you deploy code. When non-technical stakeholders want a new rule,
          they file a ticket and wait. This package changes that equation entirely.
        </p>
      </div>

      <div class="why-grid">
        <div class="why-card">
          <div class="why-card-number">01</div>
          <h4>AI agents modify your app — safely</h4>
          <p>
            Expose a REST API + MCP server that AI agents use to create, edit, and execute workflows.
            Your app's behavior changes without a single line of PHP being touched.
            Agents work within the graph model — they can't break what they can't access.
          </p>
        </div>

        <div class="why-card">
          <div class="why-card-number">02</div>
          <h4>No-code for your whole team</h4>
          <p>
            The visual drag-and-drop editor lets product managers, ops teams, and support leads
            build automations themselves. New business rule? New workflow — zero deployments,
            zero pull requests, zero waiting on engineering.
          </p>
        </div>

        <div class="why-card">
          <div class="why-card-number">03</div>
          <h4>Your core codebase stays clean</h4>
          <p>
            Workflows live in the database, not in your controllers or models.
            Add ten new automation scenarios and your <code>app/</code> directory doesn't grow by a single file.
            Disable a workflow with one toggle — no rollback needed.
          </p>
        </div>

        <div class="why-card">
          <div class="why-card-number">04</div>
          <h4>Full observability, built in</h4>
          <p>
            Every workflow run is recorded: per-node input/output, execution duration, error traces,
            and retry history. Debug a failed notification chain, replay a broken import,
            or audit what your AI agent built last Tuesday.
          </p>
        </div>

        <div class="why-card">
          <div class="why-card-number">05</div>
          <h4>Extend with one PHP class</h4>
          <p>
            Need a custom node for your internal API, a Stripe charge, or a domain-specific calculation?
            Write one class with <code>#[AsWorkflowNode]</code> and it appears in the editor.
            Bundle nodes into plugins and share across projects.
          </p>
        </div>

        <div class="why-card">
          <div class="why-card-number">06</div>
          <h4>Durable execution is the target</h4>
          <p>
            The imported engine already provides graph traversal, cycle detection, retries,
            rate limiting, and pause/resume support. Aitumalow is hardening those capabilities
            around immutable publication, exact-version runs, idempotency, and authorization.
          </p>
        </div>
      </div>

      <div class="why-cta">
        <a href="/getting-started/why-use-this" class="btn btn-secondary">Read the full story →</a>
      </div>
    </section>

    <!-- CTA -->
    <section class="cta-section">
      <h2>Ready to automate?</h2>
      <p>Install with Composer and build your first workflow in minutes.</p>
      <div class="cta-code">
        <code>composer require aitumalow/aitumalow</code>
      </div>
      <div class="hero-actions" style="margin-top: 2rem;">
        <a href="/getting-started/installation" class="btn btn-primary">Read the docs</a>
      </div>
    </section>
  </div>
</template>

<style scoped>
.home-page {
  max-width: 1152px;
  margin: 0 auto;
  padding: 0 24px;
  position: relative;
  overflow-x: hidden;
}

.grid-bg {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  pointer-events: none;
  z-index: 0;
}

/* Ensure all sections sit above the grid */
.hero, .screenshot-section, .edge-divider, .code-section,
.features-section, .why-section, .cta-section {
  position: relative;
  z-index: 1;
}

/* Hero */
.hero {
  padding: 80px 0 40px;
  text-align: center;
}

.hero-content {
  max-width: 720px;
  margin: 0 auto;
}

.hero-badge {
  display: inline-block;
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 500;
  letter-spacing: 0.02em;
  color: var(--vp-c-brand-1);
  background: var(--vp-c-brand-soft);
  margin-bottom: 24px;
}

.hero-title {
  font-size: 56px;
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -0.03em;
  color: var(--vp-c-text-1);
  margin: 0;
}

.hero-highlight {
  background: linear-gradient(135deg, var(--vp-c-brand-1), var(--vp-c-brand-2, #6366f1));
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.hero-description {
  font-size: 18px;
  line-height: 1.7;
  color: var(--vp-c-text-2);
  margin: 24px 0 32px;
  max-width: 600px;
  margin-left: auto;
  margin-right: auto;
}

.hero-actions {
  display: flex;
  gap: 12px;
  justify-content: center;
  flex-wrap: wrap;
}

.btn {
  display: inline-flex;
  align-items: center;
  padding: 12px 24px;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s ease;
}

.btn-primary {
  background: var(--vp-c-brand-1);
  color: #fff;
}

.btn-primary:hover {
  background: var(--vp-c-brand-2, var(--vp-c-brand-1));
  opacity: 0.9;
}

.btn-secondary {
  background: var(--vp-c-bg-soft);
  color: var(--vp-c-text-1);
  border: 1px solid var(--vp-c-divider);
}

.btn-secondary:hover {
  border-color: var(--vp-c-brand-1);
  color: var(--vp-c-brand-1);
}

/* Animated Edge Divider */
.edge-divider {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 80px;
}

.edge-svg {
  width: 200px;
  height: 100%;
}

.edge-path-bg {
  fill: none;
  stroke: var(--vp-c-divider);
  stroke-width: 2;
  stroke-dasharray: 6 4;
}

.edge-path {
  fill: none;
  stroke: var(--vp-c-brand-1);
  stroke-width: 2;
  stroke-dasharray: 6 4;
  stroke-dashoffset: 0;
  animation: edgeFlow 1s linear infinite;
  opacity: 0.6;
}

@keyframes edgeFlow {
  to {
    stroke-dashoffset: -20;
  }
}

.edge-label {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

.edge-label span {
  display: inline-block;
  padding: 4px 14px;
  font-size: 12px;
  font-weight: 500;
  color: var(--vp-c-text-3);
  background: var(--vp-c-bg);
  border: 1px solid var(--vp-c-divider);
  border-radius: 999px;
  white-space: nowrap;
}

/* Code Section */
.code-section {
  padding: 0 0 60px;
}

.code-container {
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--vp-c-divider);
  background: var(--vp-c-bg-soft);
}

.code-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 16px;
  border-bottom: 1px solid var(--vp-c-divider);
  background: var(--vp-c-bg);
  overflow: hidden;
}

.code-tabs {
  display: flex;
  gap: 0;
}

.code-tab {
  padding: 12px 20px;
  font-size: 14px;
  font-weight: 500;
  color: var(--vp-c-text-3);
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  transition: all 0.2s;
}

.code-tab:hover {
  color: var(--vp-c-text-1);
}

.code-tab.active {
  color: var(--vp-c-brand-1);
  border-bottom-color: var(--vp-c-brand-1);
}

.code-filename {
  font-size: 13px;
  color: var(--vp-c-text-3);
  font-family: var(--vp-font-family-mono);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex-shrink: 1;
  min-width: 0;
}

.code-body {
  overflow-x: auto;
}

.code-body :deep(pre) {
  margin: 0;
  padding: 24px;
  font-size: 14px;
  line-height: 1.7;
  border-radius: 0;
}

.code-body :deep(code) {
  font-family: var(--vp-font-family-mono);
}

/* Shiki dual theme: show light in light mode, dark in dark mode */
html:not(.dark) .code-body :deep(.shiki),
html:not(.dark) .code-body :deep(.shiki span) {
  color: var(--shiki-light) !important;
  background-color: var(--shiki-light-bg) !important;
}

html.dark .code-body :deep(.shiki),
html.dark .code-body :deep(.shiki span) {
  color: var(--shiki-dark) !important;
  background-color: var(--shiki-dark-bg) !important;
}

/* Features */
.features-section {
  padding: 60px 0;
}

.features-header {
  text-align: center;
  margin-bottom: 48px;
}

.features-header h2 {
  font-size: 32px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--vp-c-text-1);
  margin: 0 0 12px;
}

.features-header p {
  font-size: 16px;
  color: var(--vp-c-text-2);
  margin: 0;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

@media (max-width: 640px) {
  .home-page {
    padding: 0 16px;
  }
  .hero {
    padding: 48px 0 24px;
  }
  .hero-title {
    font-size: 32px;
  }
  .hero-description {
    font-size: 15px;
  }
  .features-grid {
    grid-template-columns: 1fr;
  }
  .feature-card {
    padding: 20px;
  }
  .why-header h2 {
    font-size: 24px;
  }
  .why-card {
    padding: 20px;
  }
  .why-section {
    padding: 48px 0;
  }
  .code-filename {
    display: none;
  }
  .code-tab {
    padding: 10px 14px;
    font-size: 13px;
  }
  .code-body :deep(pre) {
    padding: 16px;
    font-size: 12px;
  }
  .cta-code {
    padding: 12px 16px;
    font-size: 13px;
  }
  .cta-section h2 {
    font-size: 24px;
  }
  .browser-address span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

@media (min-width: 641px) and (max-width: 768px) {
  .hero-title {
    font-size: 36px;
  }
  .features-grid {
    grid-template-columns: 1fr;
  }
  .feature-card {
    padding: 24px;
  }
  .why-header h2 {
    font-size: 28px;
  }
  .why-card {
    padding: 24px;
  }
  .cta-code {
    font-size: 14px;
  }
}

@media (min-width: 769px) and (max-width: 1024px) {
  .features-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

.feature-card {
  padding: 28px;
  border-radius: 12px;
  border: 1px solid var(--vp-c-divider);
  background: var(--vp-c-bg);
  transition: all 0.2s ease;
}

.feature-card:hover {
  border-color: var(--vp-c-brand-soft);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}

.feature-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  background: var(--vp-c-brand-soft);
  color: var(--vp-c-brand-1);
  margin-bottom: 16px;
}

.feature-card h3 {
  font-size: 16px;
  font-weight: 600;
  color: var(--vp-c-text-1);
  margin: 0 0 8px;
}

.feature-card p {
  font-size: 14px;
  line-height: 1.6;
  color: var(--vp-c-text-2);
  margin: 0;
}

/* Screenshot */
.screenshot-section {
  padding: 40px 0 0;
}

.screenshot-container {
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--vp-c-divider);
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.08);
}

.browser-chrome {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  background: var(--vp-c-bg);
  border-bottom: 1px solid var(--vp-c-divider);
}

.browser-dots {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
}

.dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.dot-red { background: #ff5f57; }
.dot-yellow { background: #febc2e; }
.dot-green { background: #28c840; }

.browser-nav {
  display: flex;
  align-items: center;
  gap: 4px;
  color: var(--vp-c-text-3);
  opacity: 0.5;
  flex-shrink: 0;
}

@media (max-width: 640px) {
  .browser-nav { display: none; }
}

.browser-address {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 999px;
  background: var(--vp-c-bg-soft);
  font-size: 12px;
  font-family: var(--vp-font-family-mono);
  color: var(--vp-c-text-3);
}

.browser-address svg {
  flex-shrink: 0;
  color: var(--vp-c-text-3);
  opacity: 0.6;
}

.screenshot-container img {
  width: 100%;
  display: block;
}

/* Default: use prefers-color-scheme for SSR/initial load */
.screenshot-dark { display: none; }
.screenshot-light { display: block; }
@media (prefers-color-scheme: dark) {
  .screenshot-dark { display: block; }
  .screenshot-light { display: none; }
}
/* Once VitePress hydrates, html class takes priority */
html.dark .screenshot-dark { display: block; }
html.dark .screenshot-light { display: none; }
html:not(.dark) .screenshot-dark { display: none; }
html:not(.dark) .screenshot-light { display: block; }


/* Why Section */
.why-section {
  padding: 80px 0;
}

.why-header {
  text-align: center;
  margin-bottom: 56px;
}

.why-badge {
  display: inline-block;
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 13px;
  font-weight: 500;
  letter-spacing: 0.02em;
  color: var(--vp-c-brand-1);
  background: var(--vp-c-brand-soft);
  margin-bottom: 20px;
}

.why-header h2 {
  font-size: 36px;
  font-weight: 800;
  letter-spacing: -0.03em;
  line-height: 1.2;
  color: var(--vp-c-text-1);
  margin: 0 0 20px;
}

.why-subtitle {
  font-size: 17px;
  line-height: 1.8;
  color: var(--vp-c-text-2);
  max-width: 680px;
  margin: 0 auto;
}

.why-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}

@media (min-width: 1025px) {
  .why-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

.why-card {
  padding: 32px;
  border-radius: 12px;
  border: 1px solid var(--vp-c-divider);
  background: var(--vp-c-bg);
  transition: all 0.2s ease;
}

.why-card:hover {
  border-color: var(--vp-c-brand-soft);
  box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
}

.why-card-number {
  font-size: 13px;
  font-weight: 700;
  font-family: var(--vp-font-family-mono);
  color: var(--vp-c-brand-1);
  margin-bottom: 12px;
  opacity: 0.7;
}

.why-card h4 {
  font-size: 17px;
  font-weight: 650;
  color: var(--vp-c-text-1);
  margin: 0 0 10px;
  line-height: 1.4;
}

.why-card p {
  font-size: 14px;
  line-height: 1.7;
  color: var(--vp-c-text-2);
  margin: 0;
}

.why-card code {
  font-size: 13px;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--vp-c-bg-soft);
  color: var(--vp-c-brand-1);
  font-family: var(--vp-font-family-mono);
}

.why-cta {
  text-align: center;
  margin-top: 40px;
}

/* CTA */
.cta-section {
  padding: 60px 0 80px;
  text-align: center;
}

.cta-section h2 {
  font-size: 32px;
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--vp-c-text-1);
  margin: 0 0 12px;
}

.cta-section p {
  font-size: 16px;
  color: var(--vp-c-text-2);
  margin: 0 0 24px;
}

.cta-code {
  display: inline-block;
  max-width: 100%;
  padding: 14px 28px;
  border-radius: 8px;
  background: var(--vp-c-bg-soft);
  border: 1px solid var(--vp-c-divider);
  font-family: var(--vp-font-family-mono);
  font-size: 15px;
  color: var(--vp-c-text-1);
  overflow-wrap: break-word;
  word-break: break-all;
  box-sizing: border-box;
}
</style>
