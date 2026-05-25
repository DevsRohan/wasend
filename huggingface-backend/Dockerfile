# ---------------------------------------------------------------------
# Wasend Engine - Hugging Face Spaces Docker image
# Node 20 + Chromium for whatsapp-web.js + Puppeteer
# ---------------------------------------------------------------------
FROM node:20-bookworm-slim

# Install Chromium and all required libs for headless WhatsApp Web
RUN apt-get update && apt-get install -y --no-install-recommends \
      chromium \
      ca-certificates \
      fonts-liberation \
      fonts-noto-color-emoji \
      libasound2 \
      libatk-bridge2.0-0 \
      libatk1.0-0 \
      libc6 \
      libcairo2 \
      libcups2 \
      libdbus-1-3 \
      libdrm2 \
      libexpat1 \
      libfontconfig1 \
      libgbm1 \
      libgcc-s1 \
      libglib2.0-0 \
      libgtk-3-0 \
      libnspr4 \
      libnss3 \
      libpango-1.0-0 \
      libpangocairo-1.0-0 \
      libstdc++6 \
      libx11-6 \
      libx11-xcb1 \
      libxcb1 \
      libxcomposite1 \
      libxcursor1 \
      libxdamage1 \
      libxext6 \
      libxfixes3 \
      libxi6 \
      libxrandr2 \
      libxrender1 \
      libxss1 \
      libxtst6 \
      lsb-release \
      wget \
      xdg-utils \
      curl \
      tini \
    && rm -rf /var/lib/apt/lists/*

# Hugging Face Spaces: HOME must be writable
ENV HOME=/home/user \
    PATH=/home/user/.local/bin:$PATH \
    NODE_ENV=production \
    PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium \
    CHROMIUM_PATH=/usr/bin/chromium \
    DATA_DIR=/data \
    PORT=7860

RUN useradd -m -u 1000 user
WORKDIR /home/user/app

COPY --chown=user:user package.json ./
RUN npm install --omit=dev --no-audit --no-fund

COPY --chown=user:user . .

# HF Spaces persistent storage typically mounts at /data; if not we fall back
RUN mkdir -p /data/wa_session /data/logs && \
    chown -R user:user /home/user /data || true

USER user

EXPOSE 7860

ENTRYPOINT ["/usr/bin/tini", "--"]
CMD ["node", "server.js"]
