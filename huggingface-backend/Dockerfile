# syntax=docker/dockerfile:1.6
# ---------------------------------------------------------------------
# Wasend Engine - Hugging Face Spaces Docker image
# Node 20 + Chromium + Puppeteer for whatsapp-web.js
# ---------------------------------------------------------------------
FROM node:20-slim

# Avoid interactive apt prompts during build
ENV DEBIAN_FRONTEND=noninteractive

# Install Chromium and the actual runtime libs Puppeteer needs (Bookworm).
# Trimmed list: only what's actually required + transitively pulled in.
RUN apt-get update && apt-get install -y --no-install-recommends \
        chromium \
        ca-certificates \
        fonts-liberation \
        fonts-noto-color-emoji \
        libasound2 \
        libatk-bridge2.0-0 \
        libatk1.0-0 \
        libcairo2 \
        libcups2 \
        libdbus-1-3 \
        libdrm2 \
        libgbm1 \
        libgtk-3-0 \
        libnspr4 \
        libnss3 \
        libpango-1.0-0 \
        libxcomposite1 \
        libxdamage1 \
        libxfixes3 \
        libxkbcommon0 \
        libxrandr2 \
        libxss1 \
        xdg-utils \
        tini \
        curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------
# Hugging Face Spaces requires a UID 1000 user named "user" with HOME=/home/user.
# The official node:20 image already defines UID 1000 as the "node" user, which
# would make `useradd -u 1000 user` fail. We must remove the existing node user
# first, then create the HF-required `user`.
# ---------------------------------------------------------------------
RUN userdel -r node 2>/dev/null || true \
 && useradd -m -u 1000 -s /bin/bash user

ENV HOME=/home/user \
    PATH=/home/user/.local/bin:$PATH \
    NODE_ENV=production \
    PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    PUPPETEER_SKIP_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium \
    CHROMIUM_PATH=/usr/bin/chromium \
    DATA_DIR=/data \
    PORT=7860

WORKDIR /home/user/app

# Try to pre-create /data dirs as root so they exist before HF mounts a volume.
# If /data is not writable (read-only mount, etc.) we ignore the error and let
# the runtime sessionManager fall back to an in-app data directory.
RUN mkdir -p /data/wa_session /data/logs \
    && chown -R user:user /data /home/user || true

# Switch to the unprivileged user before installing dependencies so node_modules
# is owned by the runtime user.
USER user

# Install dependencies (env vars above prevent puppeteer from trying to download
# its own Chromium during npm postinstall).
COPY --chown=user:user package.json ./
RUN npm install --omit=dev --no-audit --no-fund \
    && npm cache clean --force

# Copy app source last for better layer caching.
COPY --chown=user:user . .

EXPOSE 7860

ENTRYPOINT ["/usr/bin/tini", "--"]
CMD ["node", "server.js"]
