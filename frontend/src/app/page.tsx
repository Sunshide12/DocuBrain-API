import Link from "next/link";
import Image from "next/image";
import bgImage from "./landing/img/cerebro-entre-manos-wallpaper.jpeg";

export default function Home() {
  return (
    <main className="relative flex-1 flex flex-col justify-center items-start overflow-hidden bg-[#fdfdfd]">
      {/* Background Image */}
      <Image
        src={bgImage}
        alt="Productividad"
        fill
        className="object-cover translate-x-[12%]"
        priority
      />

      {/* Dark Overlay for text readability */}
      <div className="absolute inset-0 bg-black/60 z-0"></div>

      {/* Content Container */}
      <div className="relative z-10 max-w-2xl w-full px-6 sm:px-12 lg:px-20 py-16 text-left flex flex-col items-start gap-8">

        <h1 className="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight text-white leading-[1.15]">
          Potencia tu Productividad y Organiza tu Conocimiento
        </h1>

        <p className="text-lg sm:text-xl text-gray-200 leading-relaxed max-w-xl">
          Olvídate de las tareas manuales y la desorganización. Nuestro sistema centralizado te permite procesar documentos, encontrar respuestas al instante y automatizar tus flujos de trabajo. Empieza a ver resultados hoy mismo.
        </p>

        <div className="mt-4">
          <Link
            href="/login"
            className="inline-flex items-center justify-center rounded-xl bg-white hover:bg-zinc-100 px-10 py-4 text-lg font-semibold text-zinc-900 shadow-lg shadow-white/5 transition-all hover:-translate-y-0.5 focus:ring-4 focus:ring-white/30"
          >
            Acceder / Iniciar Sesión
          </Link>
        </div>

      </div>
    </main>
  );
}
