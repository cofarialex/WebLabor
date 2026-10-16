import { useState, useEffect } from "react";

const QUESTION_COUNT = 10; // hány kérdés legyen egy játékban
const TIME_PER_QUESTION = 15; // másodperc kérdésenként
const TIMEOUT = "__TIMEOUT__"; // ezt "választjuk", ha lejárt az idő

// Összekeveri a lista elemeit véletlenszerű sorrendbe
function shuffle(array) {
  return [...array].sort(() => Math.random() - 0.5);
}

// Betölti a mentett eredményeket a böngészőből (localStorage)
function loadScores() {
  try {
    return JSON.parse(localStorage.getItem("trivia-scores")) || [];
  } catch {
    return [];
  }
}

// Top 5 ponttábla komponens
function Leaderboard({ scores }) {
  if (scores.length === 0) {
    return <p className="muted">Még nincs mentett eredmény.</p>;
  }
  return (
    <ol className="leaderboard">
      {scores.slice(0, 5).map((s, i) => (
        <li key={i}>
          <span>
            {s.name} - {s.score}/{s.total}
          </span>
          <span className="muted">{s.difficulty}</span>
        </li>
      ))}
    </ol>
  );
}

function App() {
  // melyik képernyő látszik: start | loading | playing | finished | error
  const [screen, setScreen] = useState("start");
  const [difficulty, setDifficulty] = useState("easy");
  const [gameId, setGameId] = useState(0);
  const [questions, setQuestions] = useState([]);
  const [current, setCurrent] = useState(0);
  const [score, setScore] = useState(0);
  const [selected, setSelected] = useState(null);
  const [timeLeft, setTimeLeft] = useState(TIME_PER_QUESTION);
  const [errorMessage, setErrorMessage] = useState("");
  const [name, setName] = useState("");
  const [saved, setSaved] = useState(false);
  const [scores, setScores] = useState(loadScores);

  // 1) Kérdések lekérése, valahányszor új játék indul
  useEffect(() => {
    if (gameId === 0) return; // az elején még nem kérünk le semmit

    let cancelled = false;
    const url =
      `https://opentdb.com/api.php?amount=${QUESTION_COUNT}` +
      `&category=18&type=multiple&difficulty=${difficulty}&encode=url3986`;

    fetch(url)
      .then((response) => response.json())
      .then((data) => {
        if (cancelled) return;

        if (data.response_code === 0) {
          const prepared = data.results.map((q) => ({
            question: decodeURIComponent(q.question),
            correct: decodeURIComponent(q.correct_answer),
            answers: shuffle([...q.incorrect_answers, q.correct_answer]).map((a) =>
              decodeURIComponent(a)
            ),
          }));
          setQuestions(prepared);
          setScreen("playing");
        } else if (data.response_code === 5) {
          setErrorMessage("Túl gyorsan kértél új kérdéseket. Várj 5 másodpercet, és próbáld újra!");
          setScreen("error");
        } else {
          setErrorMessage("Nem sikerült kérdéseket betölteni ehhez a nehézséghez.");
          setScreen("error");
        }
      })
      .catch(() => {
        if (cancelled) return;
        setErrorMessage("Nincs internetkapcsolat, vagy az API nem elérhető.");
        setScreen("error");
      });

    return () => {
      cancelled = true;
    };
  }, [gameId, difficulty]);

  // 2) Időzítő: másodpercenként csökken, 0-nál "lejárt az idő"
  useEffect(() => {
    if (screen !== "playing" || selected !== null) return;

    const timer = setTimeout(() => {
      if (timeLeft <= 1) {
        setTimeLeft(0);
        setSelected(TIMEOUT);
      } else {
        setTimeLeft(timeLeft - 1);
      }
    }, 1000);

    return () => clearTimeout(timer);
  }, [screen, selected, timeLeft]);

  // 3) Válasz után 1 másodperc múlva jön a következő kérdés
  useEffect(() => {
    if (selected === null) return;

    const timer = setTimeout(() => {
      setSelected(null);
      setTimeLeft(TIME_PER_QUESTION);
      if (current + 1 < questions.length) {
        setCurrent(current + 1);
      } else {
        setScreen("finished");
      }
    }, 1000);

    return () => clearTimeout(timer);
  }, [selected, current, questions.length]);

  function handleAnswer(answer) {
    if (selected !== null) return; // csak egyszer lehessen válaszolni
    setSelected(answer);
    if (answer === questions[current].correct) {
      setScore(score + 1);
    }
  }

  function startGame(level) {
    setDifficulty(level);
    setCurrent(0);
    setScore(0);
    setSelected(null);
    setTimeLeft(TIME_PER_QUESTION);
    setName("");
    setSaved(false);
    setScreen("loading");
    setGameId(gameId + 1);
  }

  function saveScore() {
    const trimmed = name.trim();
    if (trimmed === "") return;

    const newScores = [
      ...scores,
      { name: trimmed, score: score, total: questions.length, difficulty: difficulty },
    ].sort((a, b) => b.score / b.total - a.score / a.total);

    setScores(newScores);
    try {
      localStorage.setItem("trivia-scores", JSON.stringify(newScores));
    } catch {
      // ha a böngésző nem engedi a mentést, csak a memóriában marad
    }
    setSaved(true);
  }

  // ---------- KÉPERNYŐK ----------

  if (screen === "start") {
    return (
      <div className="app">
        <h1>🎮 Trivia Battle</h1>
        <p>Válassz nehézséget:</p>
        <button className="level easy" onClick={() => startGame("easy")}>Easy</button>
        <button className="level medium" onClick={() => startGame("medium")}>Medium</button>
        <button className="level hard" onClick={() => startGame("hard")}>Hard</button>

        <h3>🏆 Top 5</h3>
        <Leaderboard scores={scores} />
      </div>
    );
  }

  if (screen === "loading") {
    return (
      <div className="app">
        <p>Kérdések betöltése...</p>
      </div>
    );
  }

  if (screen === "error") {
    return (
      <div className="app">
        <p className="danger">{errorMessage}</p>
        <button onClick={() => setScreen("start")}>Vissza</button>
      </div>
    );
  }

  if (screen === "finished") {
    return (
      <div className="app fade-in">
        <h1>Vége a játéknak! 🎉</h1>
        <h2>
          {score}/{questions.length} helyes válasz
        </h2>
        <p className="muted">Nehézség: {difficulty}</p>

        {saved ? (
          <p className="success">Eredmény elmentve! ✅</p>
        ) : (
          <div className="save-row">
            <input
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Add meg a neved"
              maxLength={20}
            />
            <button onClick={saveScore} disabled={name.trim() === ""}>
              Mentés
            </button>
          </div>
        )}

        <h3>🏆 Top 5</h3>
        <Leaderboard scores={scores} />

        <button onClick={() => setScreen("start")}>Új játék</button>
      </div>
    );
  }

  // screen === "playing"
  const question = questions[current];
  const answeredCount = current + (selected !== null ? 1 : 0);
  const progressPercent = (answeredCount / questions.length) * 100;
  const timePercent = (timeLeft / TIME_PER_QUESTION) * 100;

  return (
    <div className="app">
      <div className="info-row">
        <span>
          Kérdés {current + 1}/{questions.length}
        </span>
        <span>Pont: {score}</span>
      </div>

      {/* Haladás: hány kérdésen vagy túl */}
      <div className="bar">
        <div className="bar-fill progress" style={{ width: progressPercent + "%" }} />
      </div>

      {/* Időzítő */}
      <div className="info-row">
        <span className={timeLeft <= 3 ? "danger" : ""}>⏱ {timeLeft} mp</span>
      </div>
      <div className="bar">
        <div
          className={"bar-fill timer" + (timeLeft <= 3 ? " low" : "")}
          style={{ width: timePercent + "%" }}
        />
      </div>

      {/* a key miatt minden új kérdésnél újra lejátszódik a beúszó animáció */}
      <div key={current} className="fade-in">
        <h2>{question.question}</h2>

        {question.answers.map((answer) => {
          let className = "";
          if (selected !== null) {
            if (answer === question.correct) {
              className = "correct";
            } else if (answer === selected) {
              className = "wrong";
            }
          }
          return (
            <button
              key={answer}
              className={className}
              disabled={selected !== null}
              onClick={() => handleAnswer(answer)}
            >
              {answer}
            </button>
          );
        })}
      </div>

      {selected === TIMEOUT && <p className="danger">Lejárt az idő! ⏰</p>}
    </div>
  );
}

export default App;