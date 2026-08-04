FROM python:3.12-slim

WORKDIR /app

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# Config is injected via compose environment variables (see docker-compose.yml).
ENV DB_CONNECTION=mysql
ENV MOCK_MODE=true

CMD ["python", "worker.py"]
