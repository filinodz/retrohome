const games = {
  nes: {
    name: "Nintendo Entertainment System",
    logo: "./assets/logos/nes-logo.png",
    games: [
      {
        id: "super-mario-bros",
        title: "Super Mario Bros",
        description: "Le jeu qui a défini une génération ! Accompagnez Mario dans sa quête pour sauver la Princesse Peach du maléfique Bowser. Parcourez 8 mondes remplis de champignons, de blocs mystères et d'ennemis emblématiques.",
        year: 1985,
        publisher: "Nintendo",
        cover: "./assets/covers/nes/super-mario-bros.png",
        rom: "./roms/nes/super_mario_bros.nes"
      }
      // Ajoutez d'autres jeux NES ici
    ]
  },
  snes: {
    name: "Super Nintendo",
    logo: "./assets/logos/snes-logo.png",
    games: []
  },
  psx: {
    name: "PlayStation",
    logo: "./assets/logos/psx-logo.png",
    games: []
  }
  // Ajoutez d'autres consoles ici
};

export default games;
