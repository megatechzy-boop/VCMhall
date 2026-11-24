// Cloudflare Worker: relays Meta's WhatsApp webhook to the site, because the host's
// bot protection blocks Meta's IPs. The body and signature pass through untouched,
// so whatsapp-webhook.php still verifies every POST with the App Secret.
const TARGET = 'https://venutaihall.com/whatsapp-webhook.php';

export default {
  async fetch(request) {
    const url = new URL(request.url);
    if (request.method !== 'GET' && request.method !== 'POST') return new Response('Method not allowed', { status: 405 });
    const headers = { 'User-Agent': 'venutai-wa-relay/1.0' };
    const sig = request.headers.get('X-Hub-Signature-256');
    if (sig) headers['X-Hub-Signature-256'] = sig;
    if (request.method === 'POST') headers['Content-Type'] = 'application/json';
    const res = await fetch(TARGET + url.search, {
      method: request.method,
      headers,
      body: request.method === 'POST' ? await request.arrayBuffer() : undefined,
    });
    return new Response(await res.text(), { status: res.status, headers: { 'Content-Type': 'text/plain' } });
  },
};
