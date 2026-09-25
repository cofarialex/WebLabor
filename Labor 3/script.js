// 1. Két szám összeadása
function add(a, b) {
  return a + b;
}

console.log("1. Feladat teszt (3 + 4):", add(3, 4)); // 7


// 2. Páros vagy páratlan?
function isEven(num) {
  return num % 2 === 0;
}

console.log("2. Feladat teszt (4 páros?):", isEven(4)); // true
console.log("2. Feladat teszt (5 páros?):", isEven(5)); // false


// 3. Számláló gombnyomásra
let count = 0;

function incrementCounter() {
  count++;
  document.getElementById('counter').innerText = count;
}