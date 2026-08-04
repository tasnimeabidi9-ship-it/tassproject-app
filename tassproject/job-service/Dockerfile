FROM node:20-alpine

WORKDIR /app

COPY package.json ./
RUN npm ci --omit=dev

COPY src ./src

ENV NODE_ENV=production

# The API container runs `node src/server.js`. The worker container
# overrides the command (see docker-compose.yml: job-workers).
CMD ["node", "src/server.js"]
