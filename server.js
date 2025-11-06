import express from 'express';
import morgan from 'morgan';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = process.env.PORT || 3000;

const rapidApiBaseUrl = 'https://fragrancefinder.p.rapidapi.com';
const rapidApiHost = 'fragrancefinder.p.rapidapi.com';
const rapidApiKey = process.env.RAPIDAPI_KEY || '984c793159msha2bee476bc45cfdp18e0b5jsn774465b770c1';

if (!rapidApiKey) {
  console.warn('[Findify] Nenhuma RAPIDAPI_KEY definida. Configure a variável de ambiente para chamadas autenticadas.');
}

app.use(morgan('dev'));
app.use(express.static(__dirname));

async function callRapidApi(endpoint) {
  if (!rapidApiKey) {
    const error = new Error('Chave da RapidAPI ausente. Configure RAPIDAPI_KEY no ambiente do servidor.');
    error.status = 500;
    throw error;
  }

  const url = `${rapidApiBaseUrl}${endpoint}`;

  const response = await fetch(url, {
    method: 'GET',
    headers: {
      'Accept': 'application/json',
      'X-RapidAPI-Key': rapidApiKey,
      'X-RapidAPI-Host': rapidApiHost
    }
  });

  if (!response.ok) {
    const text = await response.text();
    const error = new Error(`RapidAPI respondeu com status ${response.status}`);
    error.status = response.status;
    error.payload = text;
    throw error;
  }

  return response.json();
}

app.get('/api/perfumes/search', async (req, res) => {
  const query = (req.query.q || '').toString().trim();
  if (!query) {
    return res.status(400).json({ error: 'Parâmetro q é obrigatório.' });
  }

  try {
    const data = await callRapidApi(`/perfumes/search?q=${encodeURIComponent(query)}`);
    res.json(data);
  } catch (error) {
    console.error('Erro ao buscar perfumes na RapidAPI:', error);
    res.status(error.status || 500).json({
      error: 'Falha ao consultar a RapidAPI.',
      details: error.payload || error.message
    });
  }
});

app.get('/api/dupes/:id', async (req, res) => {
  const { id } = req.params;
  if (!id) {
    return res.status(400).json({ error: 'Informe o ID do perfume.' });
  }

  try {
    const data = await callRapidApi(`/dupes/${encodeURIComponent(id)}`);
    res.json(data);
  } catch (error) {
    console.error('Erro ao buscar dupes na RapidAPI:', error);
    res.status(error.status || 500).json({
      error: 'Falha ao consultar a RapidAPI.',
      details: error.payload || error.message
    });
  }
});

app.get('/api/health', (_req, res) => {
  res.json({ status: 'ok' });
});

app.get('*', (_req, res) => {
  res.sendFile(path.join(__dirname, 'index.html'));
});

app.listen(PORT, () => {
  console.log(`Findify app disponível em http://localhost:${PORT}`);
});
