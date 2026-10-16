export default function WeatherCard({ data }) {
  const { name, country, temp, feelsLike, humidity, wind, description, icon } = data;

  return (
    <div className="card" style={{ marginTop: '20px', padding: '20px', border: '1px solid #ccc', borderRadius: '8px', color: 'black', background: '#fff' }}>
      <h2>{name} ({country})</h2>
      <div style={{ fontSize: '64px' }}>{icon}</div>
      <p>Hőmérséklet: <strong>{Math.round(temp)} °C</strong></p>
      <p>Érzékelve: {Math.round(feelsLike)} °C</p>
      <p>Páratartalom: {humidity}%</p>
      <p>Szél: {wind} m/s</p>
      <p style={{ textTransform: 'capitalize' }}>{description}</p>
    </div>
  );
}
