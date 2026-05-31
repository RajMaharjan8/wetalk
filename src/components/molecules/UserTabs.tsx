interface UserInfo {
  name: string;
  email: string;
  photoURL?: string;
  isAvailable: boolean;
  lastMessage?: string;
  onClick: () => void;
  isActive: boolean;
  unread?: boolean;
}

export default function UserTabs({
  name,
  email,
  photoURL,
  isAvailable,
  lastMessage,
  onClick,
  isActive,
  unread,
}: UserInfo) {
  const initials = name
    .split(" ")
    .map((word) => word[0])
    .join("")
    .toUpperCase();
  return (
    <div
      className={`w-full min-h-16 flex items-center px-4 py-2 gap-4 justify-start cursor-pointer hover:bg-light-text transition-all ease-in-out ${isActive ? "bg-light-text" : ""}`}
      onClick={onClick}
    >
      <div className="relative h-14 w-14 shrink-0">
        {/* inner wrapper clips the round photo */}
        <div className="h-full w-full rounded-full bg-primary overflow-hidden flex justify-center items-center text-white font-semibold">
          {photoURL ? (
            <img
              src={photoURL}
              alt={name}
              referrerPolicy="no-referrer"
              className="h-full w-full object-cover"
            />
          ) : (
            initials
          )}
        </div>
        {/* dot lives OUTSIDE the clipped wrapper so it isn't cut off */}
        {isAvailable && (
          <span className="absolute bottom-0 right-0 h-4 w-4 bg-green-500 rounded-full border-2 border-white" />
        )}
      </div>
      <div className="text-gray-600 min-w-0 flex-1">
        <h3 className={`flex items-center gap-2 ${unread ? "font-bold text-gray-900" : "font-semibold"}`}>
          <span className="truncate">{name}</span>
          {/* unread dot */}
          {unread && <span className="h-2.5 w-2.5 rounded-full bg-primary shrink-0" />}
        </h3>
        {lastMessage ? (
          // show the conversation preview only if we've actually chatted
          <p
            className={`text-sm line-clamp-1 break-all ${
              unread ? "font-semibold text-gray-900" : "font-light text-gray-500"
            }`}
          >
            {lastMessage}
          </p>
        ) : (
          <div className="flex gap-2 font-light text-sm">
            <span>{isAvailable ? "Available" : "Offline"} </span>
            <span className="break-all line-clamp-1">{email}</span>
          </div>
        )}
      </div>
    </div>
  );
}
