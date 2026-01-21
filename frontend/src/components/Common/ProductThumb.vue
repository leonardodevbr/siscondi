<template>
  <div
    :class="[
      'rounded overflow-hidden bg-gray-100 flex items-center justify-center',
      sizeClass || size,
      imageError || !src ? 'cursor-default' : 'cursor-pointer hover:scale-150 transition-transform',
    ]"
    @click="handleClick"
    :title="alt"
  >
    <img
      v-if="src && !imageError"
      :src="src"
      :alt="alt"
      class="h-full w-full object-cover"
      @error="handleImageError"
    />
    <div v-else class="flex flex-col items-center justify-center text-gray-400">
      <PhotoIcon class="h-6 w-6" />
    </div>
  </div>
</template>

<script>
import { ref } from 'vue';
import { PhotoIcon } from '@heroicons/vue/24/outline';

export default {
  name: 'ProductThumb',
  components: {
    PhotoIcon,
  },
  props: {
    src: {
      type: String,
      default: null,
    },
    alt: {
      type: String,
      default: 'Product image',
    },
    size: {
      type: String,
      default: 'h-10 w-10',
    },
    sizeClass: {
      type: String,
      default: null,
    },
  },
  emits: ['click'],
  setup(props, { emit }) {
    const imageError = ref(false);

    const handleImageError = () => {
      imageError.value = true;
    };

    const handleClick = () => {
      if (props.src && !imageError.value) {
        emit('click', props.src);
      }
    };

    return {
      imageError,
      handleImageError,
      handleClick,
    };
  },
};
</script>
