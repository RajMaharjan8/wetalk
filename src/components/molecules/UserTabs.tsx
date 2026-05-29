interface UserInfo {
  name: string;
  email: string;
  photoURL?: string;
  isAvailable: boolean;
  onClick: () => void;
  isActive: boolean;
}

export default function UserTabs({
  name,
  email,
  photoURL,
  isAvailable,
  onClick,
  isActive,
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
      <div className="h-14 w-14 aspect-[1/1] bg-primary rounded-full flex justify-center items-center relative text-white overflow-hidden">
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
        {isAvailable ? (
          <div className="h-4 w-4 bg-green-600 rounded-full border-2 border-white absolute bottom-0 right-0"></div>
        ) : (
          ""
        )}
      </div>
      <div className="text-gray-600">
        <h3 className="font-semibold">{name}</h3>
        <div className="flex gap-2 font-light text-sm">
          <span>{isAvailable ? "Available" : "Offline"} </span>
          <span className="break-all line-clamp-1">{email}</span>
        </div>
      </div>
    </div>
  );
}
