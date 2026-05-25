// PM2 process manager config (optional - useful for non-HF deployments)
module.exports = {
  apps: [
    {
      name: 'wasend-engine',
      script: 'server.js',
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      watch: false,
      max_memory_restart: '900M',
      env: {
        NODE_ENV: 'production',
        PORT: 7860,
      },
    },
  ],
};
