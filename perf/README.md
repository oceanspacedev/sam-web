Performance test setup (Artillery)

Prerequisites
- Node.js >= 16
- Install Artillery: `npm i -g artillery@2`

Env vars
- BASE_URL: API base URL (e.g. http://localhost:8000)
- USERNAME: Test user username
- PASSWORD: Test user password
- OUTLET_CODE: Existing outlet code for visit & outlet update

Run
- Login + visit check-in/out:
  artillery run perf/artillery-visit.yml \
    -e dev \
    -o perf/out/visit-report.json \
    --overrides "{\"variables\":{\"BASE_URL\":\"$BASE_URL\",\"USERNAME\":\"$USERNAME\",\"PASSWORD\":\"$PASSWORD\",\"OUTLET_CODE\":\"$OUTLET_CODE\"}}"

- Login + outlet update foto+video:
  artillery run perf/artillery-outlet.yml \
    -e dev \
    -o perf/out/outlet-report.json \
    --overrides "{\"variables\":{\"BASE_URL\":\"$BASE_URL\",\"USERNAME\":\"$USERNAME\",\"PASSWORD\":\"$PASSWORD\",\"OUTLET_CODE\":\"$OUTLET_CODE\",\"PHOTO_PATH\":\"./perf/assets/photo.jpg\",\"VIDEO_PATH\":\"./perf/assets/video.mp4\"}}"

Notes
- Provide small sample files at `./perf/assets/photo.jpg` and `./perf/assets/video.mp4` before running.
- Scenarios use low RPS; adjust phases to stress more.
