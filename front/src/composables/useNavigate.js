import router from "@/router";

export default function navigate(name) {
    router.push({ name: name })
}